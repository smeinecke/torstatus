"""Thin wrapper around phpserialize for PHP frontend compatibility."""

import phpserialize


def dumps_list(items: list) -> bytes:
    """Serialize a flat list (used for family, exit policy, history arrays)."""
    # phpserialize.dumps on a list produces an a:... array in PHP terms.
    return phpserialize.dumps(items)
