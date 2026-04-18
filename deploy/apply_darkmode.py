#!/usr/bin/env python3
"""
Append Tailwind `dark:` variants to class names across Vue pages & components.

Two passes:
 1) Literal hero-gradient rewrites (exact-string replacement, idempotent).
 2) Regex-bounded token patching for simple utilities (bg-white, text-slate-*, …).

Idempotent: running twice leaves the files unchanged.
"""
import re, pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent

FILES = [
    # Top-level pages
    "resources/js/pages/sectors/Index.vue",
    "resources/js/pages/Diaspora.vue",
    "resources/js/pages/Mentorat.vue",
    "resources/js/pages/Gouvernement.vue",
    "resources/js/pages/Tarifs.vue",
    "resources/js/pages/projects/Index.vue",
    "resources/js/pages/Emploi.vue",
    "resources/js/pages/Formalisation.vue",
    "resources/js/pages/Kyc.vue",
    # Sub-pages
    "resources/js/pages/diaspora/CountryGuide.vue",
    "resources/js/pages/gouvernement/CallShow.vue",
    "resources/js/pages/mentorat/MentorProfile.vue",
    # Shared components
    "resources/js/components/InvestmentSimulator.vue",
    "resources/js/components/ProjectCard.vue",
    "resources/js/components/MentorCard.vue",
]

# ── Pass 1: hero gradient literal rewrites ─────────────────────────
HERO_GRADIENTS = [
    # (exact_before, exact_after)  — idempotent because "after" contains "before"
    (
        "bg-gradient-to-br from-rose-50 via-amber-50 to-emerald-50",
        "bg-gradient-to-br from-rose-50 via-amber-50 to-emerald-50 dark:from-rose-950/40 dark:via-amber-950/30 dark:to-emerald-950/40",
    ),
    (
        "bg-gradient-to-br from-violet-50 via-fuchsia-50 to-slate-50",
        "bg-gradient-to-br from-violet-50 via-fuchsia-50 to-slate-50 dark:from-violet-950/40 dark:via-fuchsia-950/30 dark:to-slate-900",
    ),
    (
        "bg-gradient-to-br from-sky-50 via-blue-50 to-slate-50",
        "bg-gradient-to-br from-sky-50 via-blue-50 to-slate-50 dark:from-sky-950/40 dark:via-blue-950/30 dark:to-slate-900",
    ),
    (
        "bg-gradient-to-br from-emerald-50 via-amber-50 to-violet-50",
        "bg-gradient-to-br from-emerald-50 via-amber-50 to-violet-50 dark:from-emerald-950/40 dark:via-amber-950/30 dark:to-violet-950/40",
    ),
    (
        "bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50",
        "bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-950/40 dark:via-orange-950/30 dark:to-yellow-950/40",
    ),
    (
        "bg-gradient-to-br from-emerald-50 via-teal-50 to-green-50",
        "bg-gradient-to-br from-emerald-50 via-teal-50 to-green-50 dark:from-emerald-950/40 dark:via-teal-950/30 dark:to-green-950/40",
    ),
    (
        "bg-gradient-to-br from-orange-50 via-amber-50 to-slate-50",
        "bg-gradient-to-br from-orange-50 via-amber-50 to-slate-50 dark:from-orange-950/40 dark:via-amber-950/30 dark:to-slate-900",
    ),
    (
        "bg-gradient-to-br from-rose-50 via-amber-50 to-slate-50",
        "bg-gradient-to-br from-rose-50 via-amber-50 to-slate-50 dark:from-rose-950/40 dark:via-amber-950/30 dark:to-slate-900",
    ),
]

def rewrite_gradients(text: str) -> str:
    for before, after in HERO_GRADIENTS:
        # Idempotency: only replace bare "before" not already followed by " dark:"
        # Use a negative-lookahead regex.
        pattern = re.compile(re.escape(before) + r"(?!\s+dark:)")
        text = pattern.sub(after, text)
    return text

# ── Pass 2: token → dark variant ────────────────────────────────
# Longer/more-specific tokens first.
MAP = [
    ("text-slate-900",   "dark:text-slate-100"),
    ("text-slate-800",   "dark:text-slate-200"),
    ("text-slate-700",   "dark:text-slate-200"),
    ("text-slate-600",   "dark:text-slate-300"),
    ("text-slate-500",   "dark:text-slate-400"),
    ("text-slate-400",   "dark:text-slate-500"),
    ("bg-white",         "dark:bg-slate-800"),
    ("bg-slate-50",      "dark:bg-slate-900"),
    ("bg-slate-100",     "dark:bg-slate-700"),
    ("border-slate-200", "dark:border-slate-700"),
    ("border-slate-100", "dark:border-slate-700"),
    ("divide-slate-200", "dark:divide-slate-700"),
]

def patch_tokens(text: str, token: str, dark: str) -> str:
    # Only match when bounded by whitespace / quote / backtick — never `/` or `-` or alnum.
    pattern = re.compile(r'(?P<pre>[\s"\'`])' + re.escape(token) + r'(?P<post>[\s"\'`])')
    def repl(m):
        return f"{m.group('pre')}{token} {dark}{m.group('post')}"
    new = pattern.sub(repl, text)
    # Collapse duplicate-applied pairs to stay idempotent.
    new = new.replace(f"{token} {dark} {dark}", f"{token} {dark}")
    return new

# ── Run ─────────────────────────────────────────────────────────
total_lines = 0
total_files = 0
for rel in FILES:
    p = ROOT / rel
    if not p.exists():
        print(f"  SKIP (missing): {rel}")
        continue
    orig = p.read_text(encoding="utf-8")
    new = rewrite_gradients(orig)
    for token, dark in MAP:
        new = patch_tokens(new, token, dark)
    if new != orig:
        p.write_text(new, encoding="utf-8", newline="\n")
        diff = sum(1 for a, b in zip(orig.splitlines(), new.splitlines()) if a != b)
        print(f"  {rel}: {diff} lines modified")
        total_lines += diff
        total_files += 1
    else:
        print(f"  {rel}: no change")

print(f"\nTotal: {total_files} files, {total_lines} lines modified.")
