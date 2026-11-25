#!/usr/bin/env bash
#
# Builds dist/wp-nopnoe.zip, the folder wordpress.org expects for the wp-nopnoe slug.
set -euo pipefail

cd "$(dirname "$0")/.."

slug="nopnoe"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${slug}.php" | head -1 | tr -d '\r')"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1 | tr -d '\r')"

if [ -z "${version}" ] || [ -z "${stable_tag}" ]; then
	echo "Could not read the version from ${slug}.php or readme.txt." >&2
	exit 1
fi

if [ "${version}" != "${stable_tag}" ]; then
	echo "Version mismatch: ${slug}.php says ${version}, readme.txt says ${stable_tag}." >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/${slug}"

# Copy only what ships. An ignore list is fragile: anything unlisted (a local WordPress
# for Plugin Check, a stray artifact) would silently end up in the release zip.
for item in "${slug}.php" readme.txt LICENSE includes; do
	if [ -e "${item}" ]; then
		cp -R "${item}" "dist/${slug}/"
	fi
done

(cd dist && zip -qr "${slug}.zip" "${slug}")

echo "Built dist/${slug}.zip (version ${version})"
