#!/usr/bin/env bash
# Pubblica dist/ sul server di miglioramento.com via rsync su SSH.
#
#   ./deploy.sh            prova a secco: mostra cosa cambierebbe, non tocca nulla
#   ./deploy.sh --vai      pubblica davvero
#
# Configurazione (variabili d'ambiente o file .deploy.env, escluso da git):
#   DEPLOY_DEST   destinazione rsync, es. utente@server.example:/var/www/miglioramento.com/
#   DEPLOY_PORT   porta SSH (facoltativa, predefinita 22)
set -euo pipefail
cd "$(dirname "$0")"

command -v rsync >/dev/null || { echo "Serve rsync: installalo (es. brew install rsync, apt install rsync)."; exit 1; }
[ -f .deploy.env ] && . ./.deploy.env
: "${DEPLOY_DEST:?Imposta DEPLOY_DEST, es. utente@server:/var/www/miglioramento.com/}"
PORTA="${DEPLOY_PORT:-22}"

# Si pubblica solo un sito senza segnaposto e con versioni coerenti
python3 build.py --rigoroso

VERSIONE=$(python3 -c 'import json; print(json.load(open("sito.json"))["versione"])')
if ! git diff --quiet HEAD -- . ; then
  echo "Attenzione: ci sono modifiche non salvate in git (stai pubblicando qualcosa che non è versionato)."
fi
if ! git tag --points-at HEAD | grep -qx "miglioramento-v$VERSIONE"; then
  echo "Attenzione: il commit attuale non ha il tag miglioramento-v$VERSIONE."
fi

OPZIONI=(-avz --delete --checksum -e "ssh -p $PORTA")
if [ "${1:-}" != "--vai" ]; then
  echo "== PROVA A SECCO (nessun file verrà modificato). Usa ./deploy.sh --vai per pubblicare. =="
  OPZIONI+=(--dry-run)
fi
rsync "${OPZIONI[@]}" dist/ "$DEPLOY_DEST"
echo "Sito v$VERSIONE → $DEPLOY_DEST"
