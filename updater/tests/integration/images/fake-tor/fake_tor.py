#!/usr/bin/env python3
"""Minimal Tor control-port stub for the TorStatus integration testbed.

Speaks just enough of the control protocol for ``stem.control.Controller``:
PROTOCOLINFO, AUTHENTICATE (any password accepted), SIGNAL, GETCONF,
GETINFO (desc/all-recent, ns/all, extra-info/digest/<d>), QUIT.
"""

import base64
import socketserver

TOR_VERSION = "0.4.8.99"
NICKNAME = "fake-torstatus"

FP1 = "AAAA" * 10  # 40 x 'A'
FP2 = "BBBB" * 10
IDENT1 = base64.b64encode(bytes.fromhex(FP1)).decode().rstrip("=")
IDENT2 = base64.b64encode(bytes.fromhex(FP2)).decode().rstrip("=")
EXTRA_DIGEST = "C" * 64

PUB_KEY = """-----BEGIN RSA PUBLIC KEY-----
MIIBCgKCAQEAuGZGbTYof3Cbx4bGbTaTvsuC0aJgZpXrLCmhpO5+laC4f5HlBTcf
fakefakefakefakefakefakefakefakefakefakefakefakefakefakefakefa
-----END RSA PUBLIC KEY-----"""

DESCRIPTOR = f"""router FakeRelay1 51.15.37.10 9001 0 9030
or-address [2001:db8::10]:9001
bandwidth 10000000 20000000 3000000
platform Tor {TOR_VERSION} on Linux
published 2024-01-01 12:00:00
fingerprint {' '.join(FP1[i:i + 4] for i in range(0, 40, 4))}
uptime 86400
extra-info-digest {EXTRA_DIGEST}
onion-key
{PUB_KEY}
signing-key
{PUB_KEY}
contact test@example.com
family ${FP2}
accept 1.2.3.0/24:80
accept *:*
reject *:25
accept6 [2001:db8::]/64:443
reject6 *:*
read-history 2024-01-01 12:00:00 (900 s) 100,200,300
write-history 2024-01-01 12:00:00 (900 s) 50,150,250
router-signature
-----BEGIN SIGNATURE-----
ZmFrZXNpZw==
-----END SIGNATURE-----
router FakeRelay2 198.51.100.20 443 0 80
bandwidth 5000000 10000000 1500000
platform Tor {TOR_VERSION} on FreeBSD
published 2024-01-01 11:00:00
fingerprint {' '.join(FP2[i:i + 4] for i in range(0, 40, 4))}
uptime 43200
onion-key
{PUB_KEY}
signing-key
{PUB_KEY}
contact bob@example.com
reject *:*
reject6 *:*
router-signature
-----BEGIN SIGNATURE-----
ZmFrZXNpZw==
-----END SIGNATURE-----
"""

CONSENSUS = f"""network-status-version 3
vote-status consensus
consensus-method 32
valid-after 2024-01-01 12:00:00
fresh-until 2024-01-01 13:00:00
valid-until 2024-01-01 15:00:00
r FakeRelay1 {IDENT1} aaaa 2024-01-01 12:00:00 51.15.37.10 9001 9030
s Authority Exit Fast Guard HSDir Named Running Stable V2Dir Valid
w Bandwidth=5000
p accept 80,443
r FakeRelay2 {IDENT2} bbbb 2024-01-01 11:00:00 198.51.100.20 443 80
s Exit Fast Running Stable Valid
w Bandwidth=3000
p reject 1-65535
directory-footer
"""

EXTRA_INFO = """extra-info FakeRelay1 {fp1}
published 2024-01-01 12:00:00
read-history 2024-01-01 12:00:00 (900 s) 400,500,600
write-history 2024-01-01 12:00:00 (900 s) 700,800,900
""".format(fp1=FP1)


def _info_value(key: str) -> str | None:
    if key == "desc/all-recent":
        return DESCRIPTOR
    if key == "ns/all":
        return CONSENSUS
    if key == "version":
        return TOR_VERSION
    if key.startswith("extra-info/digest/"):
        return EXTRA_INFO
    if key == "config/names":
        return "Nickname"
    return None


class ControlHandler(socketserver.StreamRequestHandler):
    def _reply(self, text: str) -> None:
        self.wfile.write(text.encode() + b"\r\n")

    def handle(self) -> None:
        while True:
            raw = self.rfile.readline()
            if not raw:
                return
            line = raw.decode(errors="replace").strip()
            if not line:
                continue

            verb = line.split()[0].upper()

            if verb == "PROTOCOLINFO":
                self._reply("250-PROTOCOLINFO 1")
                self._reply("250-AUTH METHODS=NULL,HASHEDPASSWORD")
                self._reply(f'250-VERSION Tor="{TOR_VERSION}"')
                self._reply("250 OK")
            elif verb == "AUTHENTICATE":
                self._reply("250 OK")
            elif verb in ("SIGNAL", "SETEVENTS", "USEFEATURE", "SETCONF", "SETEVENTS"):
                self._reply("250 OK")
            elif verb == "GETCONF":
                key = line.split()[1] if len(line.split()) > 1 else ""
                if key.lower() == "nickname":
                    self._reply(f"250-Nickname={NICKNAME}")
                self._reply("250 OK")
            elif verb == "GETINFO":
                key = line.split()[1] if len(line.split()) > 1 else ""
                value = _info_value(key)
                if value is None:
                    self._reply(f'552 Unrecognized key "{key}"')
                elif "\n" in value:
                    self._reply(f"250+{key}=")
                    self.wfile.write(value.replace("\n", "\r\n").encode())
                    self._reply(".")
                    self._reply("250 OK")
                else:
                    self._reply(f"250-{key}={value}")
                    self._reply("250 OK")
            elif verb == "QUIT":
                self._reply("250 closing connection")
                return
            else:
                self._reply(f'552 Unrecognized command "{line}"')


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == "__main__":
    with Server(("0.0.0.0", 9051), ControlHandler) as srv:
        srv.serve_forever()
