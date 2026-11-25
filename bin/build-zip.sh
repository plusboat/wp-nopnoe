#!/usr/bin/env bash
#
# Builds dist/nopnoe.zip, the folder wordpress.org expects for the nopnoe slug.
set -euo pipefail

cd "$(dirname "$0")/.."

slug="nopnoe"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' nopnoe.php | head -1 | tr -d '\r')"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1 | tr -d '\r')"

if [ -z "${version}" ] || [ -z "${stable_tag}" ]; then
	echo "Could not read the version from nopnoe.php or readme.txt." >&2
	exit 1
fi

if [ "${version}" != "${stable_tag}" ]; then
	echo "Version mismatch: nopnoe.php says ${version}, readme.txt says ${stable_tag}." >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/${slug}"
rsync -a --exclude-from=.distignore ./ "dist/${slug}/"
(cd dist && zip -qr "${slug}.zip" "${slug}")

echo "Built dist/${slug}.zip (version ${version})"
