#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utile.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v ssh >/dev/null
command -v scp >/dev/null

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
noc_root_dir="${NOC_ROOT_DIR:?Missing NOC_ROOT_DIR}"

local_index_php="$tmpdir/index.php"
remote_index_php="$noc_root_dir/index.php"

echo "Rendering NOC index.php..."
scripts/render-template.sh \
  templates/noc/index.template.php \
  "$local_index_php"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading index.php..."
scp "$local_index_php" "${oderland_user}@${oderland_host}:${remote_index_php}"

echo -n "Making index.php executable... "
execute_remote_command "chmod +x '$remote_index_php'"
echo "OK"

echo -n "Verifying index.php... "
execute_remote_command "test -x '$remote_index_php'"
echo "OK"

echo "Oderland NOC index.php deployed."
