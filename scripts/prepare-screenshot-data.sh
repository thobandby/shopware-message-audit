#!/usr/bin/env bash
set -euo pipefail

bash ./scripts/seed-demo-data.sh
bash ./scripts/seed-mh-demo.sh

echo "Screenshot-Daten vorbereitet."
echo "Pruefe im Admin:"
echo "- Bestellungen aus den Shopware-Demo-Daten"
echo "- Messenger Audit mit Demo-Nachrichten und Aktionen"
