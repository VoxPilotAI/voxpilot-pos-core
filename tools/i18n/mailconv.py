#!/usr/bin/env python3
"""Turns TastyIgniter's English mail templates into translated overrides.

For every line with English words, the line becomes a lang() call on igniter.voxpilot::mail.<template>.<key>;
blade expressions in it become :placeholders. Writes the override views and mail_en.json (strings).
Usage: mailconv.py <pos-core root>
"""
import json
import os
import re
import sys

ROOT = sys.argv[1]
OUT = os.path.join(ROOT, "extensions/igniter/voxpilot/resources/views/overrides")
SOURCES = {
    "igniter.cart": ["order", "order_alert", "order_update", "low_stock_alert"],
    "igniter.reservation": ["reservation", "reservation_alert", "reservation_reminder", "reservation_update"],
    "igniter.user": ["registration", "registration_alert", "invite_customer", "activation", "password_reset", "password_reset_request"],
    "igniter.local": ["review_chase"],
    "igniter.frontend": ["contact"],
}
EXPR = re.compile(r"\{\{\s*(.+?)\s*\}\}|\{!!\s*(.+?)\s*!!\}")
WORD = re.compile(r"[A-Za-z]{2,}")
strings = {}


def placeholder_name(expr, used):
    m = re.fullmatch(r"\$([a-zA-Z_][a-zA-Z0-9_]*)", expr.strip())
    name = m.group(1) if m else f"p{len(used) + 1}"
    while name in used:
        name += "_"
    used.append(name)
    return name


def convert_line(tpl, line, counter):
    stripped = line.strip()
    if not stripped or stripped.startswith("@") or stripped == "==" or stripped.startswith("<") and not WORD.search(re.sub(r"<[^>]+>", "", stripped)):
        return line
    text_only = EXPR.sub("", re.sub(r"<[^>]+>", "", stripped))
    if not WORD.search(text_only.replace("**", "")):
        return line
    used, params = [], []

    def repl(m):
        raw = m.group(1) or m.group(2)
        name = placeholder_name(raw, used)
        params.append((name, raw, bool(m.group(2))))
        return f":{name}"

    template = EXPR.sub(repl, stripped)
    key = f"l{counter}"
    strings.setdefault(tpl, {})[key] = template
    indent = line[: len(line) - len(line.lstrip())]
    has_markup = "<" in template or bool(params and any(raw for _, _, raw in params))
    args = ", ".join(f"'{n}' => {'(string) (' + e + ')' if raw else 'e(' + e + ')'}" for n, e, raw in params) if has_markup else ", ".join(f"'{n}' => {e}" for n, e, _ in params)
    call = f"lang('igniter.voxpilot::mail.{tpl}.{key}'" + (f", [{args}]" if args else "") + ")"
    return indent + ("{!! " + call + " !!}" if has_markup else "{{ " + call + " }}")


for ns, names in SOURCES.items():
    ext = "ti-ext-" + ns.split(".")[1]
    for name in names:
        src = os.path.join(ROOT, "vendor/tastyigniter", ext, "resources/views/mail", name + ".blade.php")
        text = open(src).read()
        tpl = name
        out_lines = []
        counter = 0
        for line in text.split("\n"):
            m = re.match(r'^subject = "(.*)"\s*$', line)
            if m:
                used, params = [], []

                def repl(mm):
                    raw = mm.group(1) or mm.group(2)
                    n = placeholder_name(raw, used)
                    params.append((n, raw))
                    return f":{n}"

                subject = EXPR.sub(repl, m.group(1))
                strings.setdefault(tpl, {})["subject"] = subject
                args = ", ".join(f"'{n}' => {e}" for n, e in params)
                out_lines.append(f'subject = "{{{{ lang(\'igniter.voxpilot::mail.{tpl}.subject\'' + (f", [{args}]" if args else "") + ") }}\"")
                continue
            counter += 1
            out_lines.append(convert_line(tpl, line, counter))
        dest = os.path.join(OUT, ns, "mail", name + ".blade.php")
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        header = f"{{{{-- VoxPilot: {ns}::mail.{name} in the recipient's language (generated from TastyIgniter's English template). --}}}}\n"
        open(dest, "w").write("\n".join(out_lines))

# Same keys in every template file of the same name across namespaces are unique (names differ).
json.dump(strings, open(os.path.join(os.path.dirname(__file__), "mail_en.json"), "w"), ensure_ascii=False, indent=1)
print(sum(len(v) for v in strings.values()), "strings in", len(strings), "templates")
