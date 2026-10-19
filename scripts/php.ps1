$PhpArguments = $args
# PHP compatible en PATH. PHPRC se transmite a los subprocesos de Artisan/Composer.
$phpPath = (Get-Command php.exe -ErrorAction Stop).Source
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskIni = Join-Path $taskRoot 'storage/runtime/php.ini'
if (!(Test-Path -LiteralPath $taskIni)) {
    $sourceIni = & $phpPath -r 'echo php_ini_loaded_file();'
    New-Item -ItemType Directory -Path (Split-Path $taskIni -Parent) -Force | Out-Null
    if ($sourceIni -and (Test-Path -LiteralPath $sourceIni)) { Copy-Item -LiteralPath $sourceIni -Destination $taskIni }
    else { New-Item -ItemType File -Path $taskIni | Out-Null }
    $extensionPath = Join-Path (Split-Path $phpPath -Parent) 'ext'
    Add-Content -LiteralPath $taskIni -Value "`nextension_dir=`"$extensionPath`""
    & $phpPath -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) { Add-Content -LiteralPath $taskIni -Value 'extension=pdo_pgsql' }
}
$env:PHPRC = $taskIni
& $phpPath @PhpArguments
exit $LASTEXITCODE
