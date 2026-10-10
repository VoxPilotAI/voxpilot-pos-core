#!/usr/bin/env python3
"""Translates the English TastyIgniter/Laravel strings (en.json) into the POS languages.

Usage: translate.py <locale> [<locale> ...]   (OPENAI_API_KEY in the environment)
Writes <locale>.json next to this file (resumable: already translated keys are kept).
"""
import concurrent.futures as cf
import json
import os
import re
import sys
import time
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
MODEL = os.environ.get("I18N_MODEL", "gpt-5.4-mini")
BATCH = 80

LANGS = {
    "es": "Spanish (Latin America, neutral; use 'tú' for customers on the storefront, impersonal/neutral wording in the admin)",
    "de": "German (use 'Sie')",
    "fr": "French (use 'vous')",
    "it": "Italian",
    "pt": "European Portuguese (pt-PT)",
    "nl": "Dutch",
}

GLOSSARY = {
    "es": "order=pedido; orders=pedidos; menu item/menu=plato/menú; location=local; staff=personal; customer=cliente; delivery=a domicilio (fulfilment) / entrega; pickup/collection=para recoger; mealtime=horario de comida; coupon=cupón; reservation=reserva; dashboard=panel; status=estado; settings=configuración; table=mesa; review=reseña; allergen=alérgeno; inventory/stock=inventario; extension=extensión; theme=tema.",
    "de": "order=Bestellung; menu item=Gericht/Menüpunkt; location=Standort; staff=Mitarbeiter; customer=Kunde; delivery=Lieferung; pickup/collection=Abholung; mealtime=Essenszeit; coupon=Gutschein; reservation=Reservierung; dashboard=Übersicht; status=Status; settings=Einstellungen; review=Bewertung; inventory=Bestand.",
    "fr": "order=commande; menu item=plat/article du menu; location=établissement; staff=personnel; customer=client; delivery=livraison; pickup/collection=à emporter; mealtime=horaire de repas; coupon=coupon; reservation=réservation; dashboard=tableau de bord; status=statut; settings=paramètres; review=avis; inventory=stock.",
    "it": "order=ordine; menu item=piatto/voce del menu; location=sede; staff=personale; customer=cliente; delivery=consegna; pickup/collection=ritiro; mealtime=orario dei pasti; coupon=coupon; reservation=prenotazione; dashboard=pannello; status=stato; settings=impostazioni; review=recensione; inventory=magazzino.",
    "pt": "order=pedido; menu item=prato/artigo do menu; location=espaço; staff=equipa; customer=cliente; delivery=entrega; pickup/collection=recolha; mealtime=horário de refeição; coupon=cupão; reservation=reserva; dashboard=painel; status=estado; settings=definições; review=avaliação; inventory=inventário.",
    "nl": "order=bestelling; menu item=gerecht/menu-item; location=vestiging; staff=personeel; customer=klant; delivery=bezorging; pickup/collection=afhalen; mealtime=maaltijdtijd; coupon=coupon; reservation=reservering; dashboard=dashboard; status=status; settings=instellingen; review=beoordeling; inventory=voorraad.",
}

TOKEN = re.compile(r":[a-zA-Z_]+|%(?:\d+\$)?[sdf]|\{[^}]*\}|<[^>]+>|&[a-z]+;|\[[^\]]*\]")


def tokens(text):
    return sorted(TOKEN.findall(text))


def ask(locale, batch):
    prompt = (
        f"Translate the values of this JSON object from English into {LANGS[locale]} for the admin panel and online ordering site "
        "of a restaurant point of sale (POS) (strings may be email copy; keep Markdown such as ** and ## as it is). Return a JSON object with exactly the same keys.\n"
        "Rules: keep every placeholder exactly as it is (:name, :attribute, %s, %d, {0}, {count}), every HTML tag and entity, "
        "URLs, and product names (TastyIgniter, VoxPilot, Stripe, PayPal, Google, Mailgun, Twilio…). Keep the same capitalisation "
        "style (a Title Case English label stays a short label). Keep pipe-separated plural forms (one|many) with the same number "
        "of parts. Short, natural UI wording; never add explanations.\n"
        f"Glossary: {GLOSSARY[locale]}\n\n" + json.dumps(batch, ensure_ascii=False)
    )
    body = json.dumps({
        "model": MODEL,
        "messages": [{"role": "user", "content": prompt}],
        "response_format": {"type": "json_object"},
    }).encode()
    for attempt in range(5):
        try:
            req = urllib.request.Request(
                "https://api.openai.com/v1/chat/completions",
                data=body,
                headers={"Authorization": f"Bearer {os.environ['OPENAI_API_KEY']}", "Content-Type": "application/json"},
            )
            with urllib.request.urlopen(req, timeout=180) as res:
                data = json.load(res)
            return json.loads(data["choices"][0]["message"]["content"])
        except Exception as ex:  # rate limits, timeouts, bad JSON
            print(f"  [{locale}] retry {attempt + 1}: {str(ex)[:120]}", file=sys.stderr)
            time.sleep(4 * (attempt + 1))
    return {}


def valid(src, out):
    return isinstance(out, str) and out.strip() != "" and tokens(src) == tokens(out) and src.count("|") == out.count("|")


def run(locale):
    src = os.environ.get("I18N_SRC", "en.json")
    en = json.load(open(os.path.join(HERE, src)))
    path = os.path.join(HERE, src.replace("en.json", f"{locale}.json"))
    done = json.load(open(path)) if os.path.exists(path) else {}
    jobs = []
    for group, strings in en.items():
        todo = {k: v for k, v in strings.items() if not valid(v, done.get(group, {}).get(k))}
        items = list(todo.items())
        for i in range(0, len(items), BATCH):
            jobs.append((group, dict(items[i:i + BATCH])))
    print(f"[{locale}] {len(jobs)} batches", file=sys.stderr)
    with cf.ThreadPoolExecutor(max_workers=8) as pool:
        futures = {pool.submit(ask, locale, batch): (group, batch) for group, batch in jobs}
        for fut in cf.as_completed(futures):
            group, batch = futures[fut]
            out = fut.result()
            for k, src in batch.items():
                if valid(src, out.get(k)):
                    done.setdefault(group, {})[k] = out[k]
    json.dump(done, open(path, "w"), ensure_ascii=False, indent=1, sort_keys=True)
    missing = [(g, k) for g, s in en.items() for k, v in s.items() if not valid(v, done.get(g, {}).get(k))]
    print(f"[{locale}] translated {sum(len(s) for s in done.values())}, still missing {len(missing)}: {missing[:5]}", file=sys.stderr)


if __name__ == "__main__":
    for loc in sys.argv[1:]:
        run(loc)
