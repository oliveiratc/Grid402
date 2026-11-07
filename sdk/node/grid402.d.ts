export declare const DEFAULT_GRID402_URL: string;

export interface CategorySummary {
    status: 'UP' | 'DEGRADED' | 'DOWN';
    avg_latency_ms: number;
    targets_up: number;
    targets_total: number;
}

export interface SummaryResponse {
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    version: string;
    timestamp: string;
    execution_time_ms: number;
    total_targets: number;
    healthy_targets: number;
    system_status: 'UP' | 'DEGRADED' | 'DOWN';
    categories: Record<string, CategorySummary>;
    notice?: string;
}

export interface X402Challenge {
    error: 'PaymentRequired';
    message: string;
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    token: string;
    token_contract: string;
    price_usdc: string;
    price_raw_units: number;
    recipient_wallet: string;
    action_required: string;
    docs: string;
}

export interface TargetDetail {
    id: string;
    name: string;
    category: string;
    status: 'UP' | 'DEGRADED' | 'DOWN' | 'TIMEOUT';
    http_code: number;
    latency_ms: number;
    connect_time_ms: number;
    dns_time_ms: number;
    primary_ip: string;
    stability_score: number;
    error_detail: string | null;
    last_check: string;
}

export interface DetailedTelemetry {
    protocol: 'x402';
    license: 'PAID_QUERY';
    timestamp: string;
    total_targets: number;
    healthy_targets: number;
    targets: TargetDetail[];
}

export declare class Grid402Error extends Error {}
export declare class PaymentRequiredError extends Grid402Error {
    challenge: X402Challenge;
    recipientWallet: string;
    priceUsdc: string;
    priceRawUnits: number;
    network: string;
    chainId: number;
    tokenContract: string;
}
export declare class ReplayAttackError extends Grid402Error {}

export interface Grid402Options {
    baseUrl?: string;
    timeout?: number;
}

export declare class Grid402Client {
    constructor(options?: Grid402Options);
    getSummary(): Promise<SummaryResponse>;
    isHealthy(targetOrCategory: string): Promise<boolean>;
    getChallenge(): Promise<X402Challenge>;
    getDetailedTelemetry(paymentTxHash: string): Promise<DetailedTelemetry>;
    guard<T>(targetOrCategory: string, action: () => Promise<T> | T, fallback?: () => Promise<T> | T): Promise<T>;
}

