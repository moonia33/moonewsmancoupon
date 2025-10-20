# Newsman Coupon Generator (moonewsmancoupon)

<a href="https://github.com/moonia33/moonewsmancoupon/releases/latest/download/moonewsmancoupon.zip">
  <img alt="Download moonewsmancoupon.zip" src="https://img.shields.io/badge/download-moonewsmancoupon.zip-blue?style=for-the-badge">
</a>

Generates single-use coupons via a webhook endpoint for Newsman automation.

## Webhook
- URL: shown in module configuration (Back Office) — looks like `/module/moonewsmancoupon/webhook`
- Auth:
  - Preferred: Header name (default `x-api-key`) with value = your API key
  - Fallback: `api_key` query parameter (e.g., `.../webhook?api_key=TOKEN`) — required because official Newsman auth flow may not work reliably

## Parameters (GET)
- `type`: 0=fixed, 1=percentage, 2=free_shipping
- `value`: required for type 0/1 (number)
- `batch_size`: how many codes to generate (1..Max)
- `prefix` (optional): code prefix (default from config)
- `expire_date` (optional): `YYYY-MM-DD HH:MM` — if missing, defaults to +N days from config
- `min_amount` (optional): minimum cart total, tax excluded
- `currency` is ignored; currency is forced to EUR and validated in the shop

## Response
JSON: `{"status":1,"codes":["CODE1","CODE2",...]}` or `{"status":0,"msg":"error"}`

## Behavior
- Each code is a separate non-combinable single-use CartRule
- Forced EUR currency; default expiry N days if not provided

## Author
- moonia — ramunas@inultimo.lt

---

# LT (Lietuviškai)

Vienkartinių kuponų generatorius per webhook'ą Newsman automatizacijoms.

## Webhook
- URL: rodomas modulio nustatymuose (BO) — formato `/module/moonewsmancoupon/webhook`
- Autentifikacija:
  - Pirminė: Header pavadinimas (numatytasis `x-api-key`) su reikšme = jūsų API raktas
  - Alternatyva: `api_key` query parametras (pvz., `.../webhook?api_key=TOKEN`) — nes Newsman dokumentuota autorizacija praktikoje neveikia stabiliai

## Parametrai (GET)
- `type`: 0=fixed, 1=percentage, 2=free_shipping
- `value`: privalomas, kai type 0/1 (skaitinė reikšmė)
- `batch_size`: kiekiui sugeneruoti (1..Max)
- `prefix` (nebūtina): kodo prefiksas (pagal nutylėjimą iš konfigūracijos)
- `expire_date` (nebūtina): `YYYY-MM-DD HH:MM` — jei nepaduotas, naudojama +N dienų iš konfigūracijos
- `min_amount` (nebūtina): minimali krepšelio suma, be PVM
- `currency` ignoruojamas; valiuta visada EUR (tikrinama, kad egzistuoja parduotuvėje)

## Atsakas
JSON: `{"status":1,"codes":["CODE1","CODE2",...]}` arba `{"status":0,"msg":"error"}`

## Elgsena
- Kiekvienas kodas — atskiras, nekombinuojamas, vienkartinis CartRule
- Valiuta EUR; jei nepaduota galiojimo data, naudojamas N dienų terminas iš konfigūracijos
