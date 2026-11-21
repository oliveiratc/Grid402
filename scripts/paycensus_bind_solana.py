#!/usr/bin/env python3
"""Vincula um endereco Solana ao perfil verificado PayCensus do grid402.online.

Spec oficial: https://trust.paycensus.com/solana-proof.html
  msg     = "paycensus-addr-proof=" + "paycensus-verify=<nonce>"  (UTF-8 exato)
  POST    https://trust.paycensus.com/api/verify/addr-proof-solana
          {"vid": 12, "addr": "<base58 pubkey>", "signature": "<base58 de 64 bytes>"}

A "addr" submetida DEVE ser a base58 da chave publica da chave que assina,
senao a verificacao falha. O nonce eh de uso unico/vinculado ao vid=12.

Fonte da chave privada (em ordem de prioridade):
  1. --keypair CAMINHO   (Solana id.json: array de 64 ints, ou arquivo com base58)
  2. env GRID402_SOLANA_SIGNING_KEY (base58 de 32 bytes = seed, ou 64 bytes = secret key)
  3. --generate          (cria um keypair novo; a seed eh impressa para guardar)

Uso:
  python scripts/paycensus_bind_solana.py --generate --dry-run   # só mostra o que faria
  python scripts/paycensus_bind_solana.py --generate             # executa o bind
"""
from __future__ import annotations

import argparse
import json
import os
import ssl
import sys
import urllib.error
import urllib.request

from nacl.signing import SigningKey
import base58

try:  # Python 3.14 no Windows nao acha a CA do sistema sem um bundle explicito
    import certifi
    _SSL_CTX = ssl.create_default_context(cafile=certifi.where())
except Exception:  # fallback: store padrao do sistema
    _SSL_CTX = ssl.create_default_context()

VID = 12
NONCE = "paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f"
MESSAGE = ("paycensus-addr-proof=" + NONCE).encode("utf-8")
ENDPOINT = "https://trust.paycensus.com/api/verify/addr-proof-solana"
PROFILE_URL = "https://trust.paycensus.com/api/profile?domain=grid402.online"
# payTo declarado em public_html/.well-known/x402.json (SPL USDC na Solana)
DECLARED_PAYTO = "CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C"


def b58(data: bytes) -> str:
    return base58.b58encode(data).decode("ascii")


def signing_key_from_b58(text: str) -> SigningKey:
    raw = base58.b58decode(text)
    if len(raw) == 64:  # secret key Solana CLI: seed(32) || pubkey(32)
        raw = raw[:32]
    if len(raw) != 32:
        raise ValueError(f"chave decodificada tem {len(raw)} bytes; esperados 32 ou 64")
    return SigningKey(raw)


def load_keypair_file(path: str) -> SigningKey:
    with open(path, "r", encoding="utf-8") as fh:
        content = fh.read().strip()
    if content.startswith("["):  # id.json
        ints = json.loads(content)
        if len(ints) not in (32, 64):
            raise ValueError(f"id.json com {len(ints)} bytes; esperados 32 ou 64")
        return SigningKey(bytes(ints[:32]))
    return signing_key_from_b58(content)


UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"


def http_json(url: str, method: str = "GET", payload: dict | None = None) -> tuple[int, dict]:
    data = None if payload is None else json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Content-Type", "application/json")
    req.add_header("User-Agent", UA)
    req.add_header("Accept", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=30, context=_SSL_CTX) as resp:
            return resp.status, json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        try:
            return exc.code, json.loads(body)
        except json.JSONDecodeError:
            # borda Cloudflare (ex.: 1010) bloqueia o urllib; tenta curl como fallback
            if exc.code in (403, 429) and "error code" in body:
                import subprocess

                cmd = ["curl", "-s", "--max-time", "30", "-X", method, "-A", UA,
                       "-H", "Content-Type: application/json", "-H", "Accept: application/json"]
                if data is not None:
                    cmd += ["--data-raw", data.decode("utf-8")]
                cmd.append(url)
                out = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
                try:
                    return int(out.returncode == 0 and 200 or exc.code), json.loads(out.stdout)
                except json.JSONDecodeError:
                    return exc.code, {"raw": out.stdout or body}
            return exc.code, {"raw": body}


def main() -> int:
    ap = argparse.ArgumentParser(description="Bind Solana address to PayCensus verified profile")
    ap.add_argument("--keypair", help="caminho para id.json ou arquivo base58 da chave")
    ap.add_argument("--generate", action="store_true", help="gera um novo keypair ed25519")
    ap.add_argument("--dry-run", action="store_true", help="mostra mensagem/assinatura sem POSTar")
    args = ap.parse_args()

    env_key = os.environ.get("GRID402_SOLANA_SIGNING_KEY", "").strip()
    if args.keypair:
        sk = load_keypair_file(args.keypair)
        key_origin = f"arquivo {args.keypair}"
    elif env_key:
        sk = signing_key_from_b58(env_key)
        key_origin = "env GRID402_SOLANA_SIGNING_KEY"
    elif args.generate:
        sk = SigningKey.generate()
        key_origin = "keypair novo gerado agora"
        print(f"[seed base58 do novo keypair - GUARDE EM SEGURO, nao versionar]:\n{b58(bytes(sk))}")
    else:
        print("erro: forneça --keypair, env GRID402_SOLANA_SIGNING_KEY ou --generate", file=sys.stderr)
        return 2

    addr = b58(sk.verify_key.encode())
    print(f"chave origem: {key_origin}")
    print(f"endereco derivado (base58): {addr}")
    if addr == DECLARED_PAYTO:
        print(">>> coincide com o payTo declarado em .well-known/x402.json (wallet de producao)")
    elif addr.upper() == "CSGJYQTWEHB3VF2VBSDMUUBPUCVX1VDEEHGVVCCF8C".upper():
        print(">>> coincide com a variante anotada no bloco de notas do Thiago")
    else:
        print(">>> endereco novo: sera esse o vinculado ao perfil (qualquer keypair seu serve)")

    print(f"mensagem assinada (UTF-8 exato): {MESSAGE.decode('utf-8')}")

    # auto-conferida local antes de gastar o nonce no servidor
    sig = sk.sign(MESSAGE).signature
    sk.verify_key.verify(MESSAGE, sig)
    sig_b58 = b58(sig)
    print(f"assinatura ed25519 (base58, {len(sig)} bytes): {sig_b58}")

    status, profile = http_json(PROFILE_URL)
    already = any(
        a.get("chain") == "solana" and a.get("addr") == addr and a.get("proven")
        for a in (profile.get("addresses") if status == 200 else [])
    )
    if already:
        print("esse endereco ja consta como proven=1 no perfil; nada a fazer.")
        return 0

    if args.dry_run:
        print("\n[dry-run] payload que seria POSTado:")
        print(json.dumps({"vid": VID, "addr": addr, "signature": sig_b58}, indent=2))
        return 0

    status, resp = http_json(ENDPOINT, method="POST", payload={"vid": VID, "addr": addr, "signature": sig_b58})
    print(f"\nPOST {ENDPOINT} -> HTTP {status}")
    print(json.dumps(resp, indent=2, ensure_ascii=False))
    if status != 200 or not resp.get("ok"):
        print("falha no bind; nao prossiga sem conferir o nonce/vid no portal.", file=sys.stderr)
        return 1

    status, profile = http_json(PROFILE_URL)
    print(f"\nGET {PROFILE_URL} -> HTTP {status}")
    for a in profile.get("addresses", []):
        print(f"  {a.get('chain'):>6} {a.get('addr')} proven={a.get('proven')}")
    bound = any(a.get("addr") == addr and a.get("proven") for a in profile.get("addresses", []))
    print("\nRESULTADO:", "VINCULADO COM PROVA CRIPTOGRAFICA (proven=1)" if bound else "NAO CONSTANTE COMO proven - investigar")
    return 0 if bound else 1


if __name__ == "__main__":
    raise SystemExit(main())
