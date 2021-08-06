#!/bin/sh

set -o errexit
set -o nounset

printf -- '- cleanup... '
rm -rf ./tmp
mkdir ./tmp
echo 'done.'

printf -- '- determining package handle... '
PACKAGE_HANDLE=$(cat controller.php | grep '$pkgHandle' | tr '"' "'" | cut -d"'" -f2)
if test -z "$PACKAGE_HANDLE"; then
    echo 'FAILED!' >&2
    exit 1
fi
printf -- 'done (%s).\n' "$PACKAGE_HANDLE"

printf -- '- exporting... '
git archive --format=tar --prefix="$PACKAGE_HANDLE/" HEAD | tar -x --directory=./tmp
echo 'done.'

printf -- '- downloading composerpkg... '
curl -sSLf -o ./tmp/composerpkg https://raw.githubusercontent.com/concrete5/cli/master/composerpkg
chmod +x ./tmp/composerpkg
echo 'done.'

printf -- '- patching composer.json... '
sed -i 's/"require-dev"/"require-dev-disabled"/' "./tmp/$PACKAGE_HANDLE/composer.json"
echo 'done.'

printf -- '- installing composer dependencies:\n'
./tmp/composerpkg install --no-interaction --working-dir="./tmp/$PACKAGE_HANDLE" --no-dev --no-suggest --prefer-dist --optimize-autoloader 2>&1 | sed 's/^/  /'

printf -- '- remove useless files... '
rm "./tmp/$PACKAGE_HANDLE/composer.json"
echo 'done.'

printf -- '- creating asset... '
cd ./tmp
zip -rqX "./$PACKAGE_HANDLE.zip" "./$PACKAGE_HANDLE"
cd ..
echo 'done.'

printf -- '- final operations... '
mv "./tmp/$PACKAGE_HANDLE.zip" .
rm -rf ./tmp
echo 'done.'
