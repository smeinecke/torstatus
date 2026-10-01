FROM python:3.12-slim

COPY fake_tor.py /usr/local/bin/fake_tor.py

HEALTHCHECK --interval=5s --timeout=3s --retries=12 \
    CMD ["python3", "-c", "import socket; socket.create_connection(('localhost', 9051), 2).close()"]

CMD ["python3", "-u", "/usr/local/bin/fake_tor.py"]
