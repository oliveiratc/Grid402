<?php
/**
 * Helpers compartilhados de ciclo de vida para o servidor web embutido do PHP.
 *
 * Motivação: varios testes sobem `php -S` em portas fixas, esperam com `usleep`
 * e encerram com `proc_terminate()`. Em Windows o `proc_open()` com string passa
 * por um `cmd.exe` intermediario, e o filho `php -S` sobrevive ao terminate,
 * mantendo a porta ocupada e travando execucoes seguintes.
 *
 * Este helper resolve os tres problemas:
 *  - porta livre alocada dinamicamente (fim das colisoes em portas fixas);
 *  - comando em formato array (sem `cmd.exe` intermediario);
 *  - teardown matando a arvore de processos de verdade.
 */

declare(strict_types=1);

/**
 * Seleciona uma porta TCP livre em loopback.
 *
 * @return int Porta disponivel, ou 0 se nenhuma pôde ser alocada.
 */
function grid402_free_port(): int
{
    $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        return 0;
    }
    $name = (string) stream_socket_get_name($sock, false);
    fclose($sock);

    $parts = explode(':', $name);
    $candidate = (int) end($parts);

    return $candidate > 0 ? $candidate : 0;
}

/**
 * Sobe o servidor web embutido do PHP e aguarda ficar pronto.
 *
 * Opções aceitas:
 *  - port:    int|null     Porta fixa (default: porta livre automatica)
 *  - host:    string       Host de bind (default: 127.0.0.1)
 *  - docroot: string       Document root (default: public_html do projeto)
 *  - router:  string|null  Router script opcional
 *  - probe:   string       Caminho usado na checagem de readiness (default: /)
 *  - timeout: float        Tempo maximo de espera (default: 10.0)
 *  - php:     string|null  Binario do PHP (default: PHP_BINARY ou "php")
 *  - log:     string|null  Arquivo de log (default: arquivo temporario)
 *  - env:     array        Variaveis de ambiente extras para o servidor (mescladas
 *                          com o ambiente atual do processo pai)
 *
 * @param array<string, mixed> $options
 * @return array{proc: resource, port: int, host: string, base_url: string, log_file: string, pipes: array, cmd: array}
 */
function grid402_start_server(array $options = []): array
{
    $host = (string) ($options['host'] ?? '127.0.0.1');
    $port = (int) ($options['port'] ?? 0);
    if ($port <= 0) {
        $port = grid402_free_port();
    }
    if ($port <= 0) {
        throw new RuntimeException('Nao foi possivel alocar uma porta livre para o servidor de teste.');
    }

    $docRoot = (string) ($options['docroot'] ?? (__DIR__ . '/../public_html'));
    if (!is_dir($docRoot)) {
        throw new RuntimeException("Document root inexistente para o servidor de teste: {$docRoot}");
    }

    $phpBinary = $options['php'] ?? null;
    if (!is_string($phpBinary) || $phpBinary === '') {
        $phpBinary = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
    }

    $logFile = (string) ($options['log'] ?? (sys_get_temp_dir() . '/grid402_test_server_' . getmypid() . '_' . $port . '.log'));
    @file_put_contents($logFile, '');

    $cmd = [$phpBinary, '-S', "{$host}:{$port}", '-t', $docRoot];
    $router = $options['router'] ?? null;
    if (is_string($router) && $router !== '') {
        $cmd[] = $router;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $logFile, 'a'],
        2 => ['file', $logFile, 'a'],
    ];

    // Ambiente do servidor: herda o processo pai e aplica overrides do teste.
    $env = null;
    if (isset($options['env']) && is_array($options['env'])) {
        $inherited = getenv();
        $env = is_array($inherited) ? $inherited : [];
        foreach ($options['env'] as $key => $value) {
            $env[(string) $key] = (string) $value;
        }
    }

    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException("Falha ao iniciar o servidor web local de teste na porta {$port}.");
    }

    $server = [
        'proc' => $proc,
        'port' => $port,
        'host' => $host,
        'base_url' => "http://{$host}:{$port}",
        'log_file' => $logFile,
        'pipes' => $pipes,
        'cmd' => $cmd,
    ];

    $probe = (string) ($options['probe'] ?? '/');
    $timeout = (float) ($options['timeout'] ?? 10.0);

    try {
        grid402_wait_for_server($server['base_url'] . $probe, $proc, $logFile, $timeout);
    } catch (Throwable $e) {
        grid402_stop_server($server);
        throw $e;
    }

    return $server;
}

/**
 * Aguarda o servidor responder, detectando encerramento precoce.
 *
 * Le os logs em arquivo (nunca em pipe) para nao travar esperando EOF.
 */
function grid402_wait_for_server(string $url, $proc, string $logFile, float $timeout = 10.0): void
{
    $deadline = microtime(true) + $timeout;

    while (microtime(true) < $deadline) {
        $status = proc_get_status($proc);
        if (empty($status['running'])) {
            $log = is_file($logFile) ? trim((string) @file_get_contents($logFile)) : '';
            throw new RuntimeException(
                'Servidor PHP encerrou antes de ficar pronto.' . ($log !== '' ? "\n" . $log : '')
            );
        }

        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code > 0) {
                return;
            }
        }

        usleep(100000); // 100ms
    }

    throw new RuntimeException("Servidor PHP nao respondeu em {$timeout}s ({$url}).");
}

/**
 * Encerra um processo e toda a sua arvore.
 *
 * No Windows usa `taskkill /T`; em sistemas POSIX mata o grupo de processos.
 * Sem isso o filho `php -S` sobrevive e mantem a porta ocupada.
 */
function grid402_kill_process_tree($proc): void
{
    if (!is_resource($proc)) {
        return;
    }

    $status = @proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);

    if ($pid > 0) {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        } else {
            @exec('kill -TERM -' . $pid . ' 2>/dev/null');
            @exec('kill -KILL -' . $pid . ' 2>/dev/null');
        }
    }

    @proc_terminate($proc);
}

/**
 * Encerra o servidor de teste e libera os recursos associados.
 *
 * @param array{proc?: resource, pipes?: array, log_file?: string} $server
 */
function grid402_stop_server(array $server): void
{
    $proc = $server['proc'] ?? null;
    if (!is_resource($proc)) {
        return;
    }

    foreach (($server['pipes'] ?? []) as $pipe) {
        if (is_resource($pipe)) {
            @fclose($pipe);
        }
    }

    grid402_kill_process_tree($proc);
    @proc_close($proc);
}

/**
 * Le o log do servidor de teste (util em mensagens de falha).
 */
function grid402_server_log(string $logFile): string
{
    return is_file($logFile) ? trim((string) @file_get_contents($logFile)) : '';
}
