#!/usr/bin/env python3
"""
Append Tailwind `dark:` variants to class names in a set of Vue pages.
Idempotent: skips a token if the expected dark variant already follows.
"""
import re, pathlib, sys

FILES = [
    "resources/js/pages/sectors/Index.vue",
    "resources/js/pages/Diaspora.vue",
    "resources/js/pages/Mentorat.vue",
    "resources/js/pages/Gouvernement.vue",
    "resources/js/pages/Tarifs.vue",
]

# order matters: longer/more-specific first so shorter ones don't partial-match
MAP = [
    ("text-slate-900", "dark:text-slate-100"),
    ("text-slate-800", "dark:text-slate-200"),
    ("text-slate-700", "dark:text-slate-200"),
    ("text-slate-600", "dark:text-slate-300"),
    ("text-slate-500", "dark:text-slate-400"),
    ("text-slate-400", "dark:text-slate-500"),
    ("bg-white",       "dark:bg-slate-800"),
    ("bg-slate-50",    "dark:bg-slate-900"),
    ("bg-slate-100",   "dark:bg-slate-700"),
    ("border-slate-200","dark:border-slate-700"),
    ("border-slate-100","dark:border-slate-700"),
    ("divide-slate-200","dark:divide-slate-700"),
]

ROOT = pathlib.Path(__file__).resolve().parent.parent

def patch(text: str, token: str, dark: str) -> str:
    # match token only when it's a standalone class token:
    #   bounded by whitespace, quote, or backtick on each side.
    # exclude if followed by '/' or '-' or alnum (e.g. bg-white/10, text-slate-900/50)
    pattern = re.compile(
        r'(?P<pre>[\s"\'`])' + re.escape(token) + r'(?P<post>[\s"\'`])'
    )
    def repl(m):
        post = m.group('post')
        # avoid double-apply: look for dark variant already present in nearby class attr
        return f"{m.group('pre')}{token} {dark}{post}"
    # First pass: naive but bounded
    new = pattern.sub(repl, text)
    # Idempotency cleanup: collapse accidental double insertions
    new = new.replace(f"{token} {dark} {dark}", f"{token} {dark}")
    return new

total = 0
for rel in FILES:
    p = ROOT / rel
    src = p.read_text(encoding="utf-8")
    orig = src
    for token, dark in MAP:
        src = patch(src, token, dark)
    if src != orig:
        # count lines changed roughly
        p.write_text(src, encoding="utf-8", newline="\n")
        diff = sum(1 for a,b in zip(orig.splitlines(), src.splitlines()) if a!=b)
        print(f"  {rel}: {diff} lines modified")
        total += diff
    else:
        print(f"  {rel}: no change")

print(f"Total: {total} lines modified.")
