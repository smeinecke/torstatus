"""Parse the shared PHP config file."""

import os
import re


def parse_config(path: str) -> dict[str, str]:
    """Parse a simple PHP config file into a Python dict.

    Supports plain string assignments and ternary-style environment fallbacks
    like ``$foo = isset($_ENV['BAR']) ? $_ENV['BAR'] : 'default';``.
    """
    config: dict[str, str] = {}
    with open(path, encoding="utf-8", errors="ignore") as fh:
        for line in fh:
            line = line.strip()
            m = re.match(r"^\$(\w+)\s*=\s*(.*?);", line)
            if not m:
                continue
            key = m.group(1)
            value = m.group(2).strip().strip('"').strip("'")

            # Resolve environment variables used in docker config
            if value.startswith("isset($_ENV["):
                env_match = re.search(
                    r"\$_ENV\['([^']+)'\]\)\s*\?\s*\$_ENV\['[^']+'\]\s*:\s*'([^']*)'",
                    line,
                )
                if env_match:
                    value = os.environ.get(env_match.group(1), env_match.group(2))
                else:
                    value = ""
            elif value.startswith("$"):
                value = config.get(value[1:], value)

            config[key] = value
    return config
