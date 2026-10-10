# POS translations

TastyIgniter ships English only. These scripts translate its strings into the POS languages
(es, de, fr, it, pt, nl) and write them where its translator reads overrides:

- `lang/<locale>/igniter/...` — core and extension strings (`dump.php` → `translate.py` → `write.php`)
- `lang/<locale>.json` — raw strings TastyIgniter passes through `lang()` (Edit, New…, seeded names)
- `extensions/igniter/voxpilot/resources/lang/<locale>/mail.php` + `resources/views/overrides/*/mail/*`
  — TastyIgniter's mail templates (`mailconv.py`, then `I18N_SRC=mail_en.json translate.py`)

Run after a TastyIgniter update that adds strings (needs `OPENAI_API_KEY`); `translate.py` keeps
what is already translated and only asks for new or changed strings. `TranslationsCompleteTest`
fails when a VoxPilot string is missing in a language.
