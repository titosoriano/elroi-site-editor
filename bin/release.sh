#!/bin/sh
# Build dist/elroi-site-editor.zip and publish it as a GitHub release of the version in the plugin header.
set -e
cd "$(dirname "$0")/.."
V=$(sed -n 's/^ \* Version: *//p' elroi-site-editor.php | tr -d ' ')
grep -q "ELROI_SE_VERSION', '$V'" elroi-site-editor.php || { echo "ELROI_SE_VERSION does not match Version: $V"; exit 1; }
for f in $(find . -name '*.php' -not -path './dist/*'); do php -l "$f" | grep -v 'No syntax' || true; done
rm -rf dist && mkdir -p dist/elroi-site-editor
cp -R elroi-site-editor.php includes assets README.md AGENTS.md dist/elroi-site-editor/
(cd dist && zip -rq elroi-site-editor.zip elroi-site-editor -x '*.DS_Store')
echo "Built dist/elroi-site-editor.zip ($V)"
if [ "$1" = "--publish" ]; then
	git diff --quiet || { echo "Commit your changes first."; exit 1; }
	git tag "v$V" && git push origin main "v$V"
	gh release create "v$V" dist/elroi-site-editor.zip --title "v$V" --notes "${NOTES:-Release $V}"
fi
