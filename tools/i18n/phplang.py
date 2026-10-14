"""Writes a flat PHP lang file: phplang.write(path, comment, {key: text})."""


def q(v):
    return "'" + v.replace("\\", "\\\\").replace("'", "\\'") + "'"


def write(path, comment, pairs):
    body = "\n".join(f"    {q(k)} => {q(v)}," for k, v in pairs.items())
    open(path, "w").write(f"<?php\n\n// {comment}\nreturn [\n{body}\n];\n")
