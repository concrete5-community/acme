#!/bin/sh

set -o errexit
set -o nounset

printf -- '- check... '
if [ ! -f ./.github/workflows/create-release-asset.sh ]; then
    echo 'INVALID FILE LOCATION!' >&2
    exit 1
fi
echo 'done.'

printf -- '- cleanup... '
rm -rf ./tmp
mkdir ./tmp
echo 'done.'

printf -- '- determining package handle... '
PACKAGE_HANDLE=$(cat controller.php | grep '$pkgHandle' | tr '"' "'" | cut -d"'" -f2)
if [ -z "$PACKAGE_HANDLE" ]; then
    echo 'FAILED!' >&2
    exit 1
fi
printf -- 'done (%s).\n' "$PACKAGE_HANDLE"

printf -- '- exporting... '
git archive --format=tar --prefix="$PACKAGE_HANDLE/" HEAD | tar -x --directory=./tmp
echo 'done.'

printf -- '- patching composer... '
cp "./tmp/$PACKAGE_HANDLE/composer.json" "./tmp/$PACKAGE_HANDLE/composer.json-original"
printf '{\n    "replace": {"phpseclib/phpseclib": "*"},\n' >"./tmp/$PACKAGE_HANDLE/composer.json"
tail +2 "./tmp/$PACKAGE_HANDLE/composer.json-original" >>"./tmp/$PACKAGE_HANDLE/composer.json"
rm "./tmp/$PACKAGE_HANDLE/composer.json-original"
echo 'done.'

printf -- '- installing composer dependencies:\n'
composer --no-interaction --ansi --working-dir="./tmp/$PACKAGE_HANDLE" update --no-dev --prefer-dist --optimize-autoloader 2>&1 | sed 's/^/  /'

printf -- '- remove useless files... '
rm "./tmp/$PACKAGE_HANDLE/composer.json"
rm "./tmp/$PACKAGE_HANDLE/composer.lock"
echo 'done.'

printf -- '- creating asset... '
(cd ./tmp && zip -rqX "./$PACKAGE_HANDLE.zip" "./$PACKAGE_HANDLE")
echo 'done.'

printf -- '- final operations... '
mv "./tmp/$PACKAGE_HANDLE.zip" .
rm -rf ./tmp
echo 'done.'
