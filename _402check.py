import ssl, urllib.request, urllib.error, json
import certifi

ctx = ssl.create_default_context(cafile=certifi.where())
req = urllib.request.Request("https://grid402.online/api/v1/crypto", headers={"User-Agent": "Grid402-Probe/1.0"})
try:
    r = urllib.request.urlopen(req, timeout=20, context=ctx)
    print("HTTP", r.status)
    print(r.read().decode())
except urllib.error.HTTPError as e:
    print("HTTP", e.code)
    print(e.read().decode())
