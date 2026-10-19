param([ValidateSet('start','stop','status')][string]$Action = 'status')
# Control opcional del cluster portable preparado en esta PC. No es un servicio.
$taskRoot = Split-Path $PSScriptRoot -Parent
$pgCtlPath = Join-Path $taskRoot 'storage/runtime/pgsql/bin/pg_ctl.exe'
$pgDataPath = Join-Path $taskRoot 'storage/runtime/pgdata17'
if (!(Test-Path -LiteralPath $pgCtlPath) -or !(Test-Path -LiteralPath $pgDataPath)) { throw 'Runtime portable no instalado. Use PostgreSQL 17 local y siga README.' }
if ($Action -eq 'start') {
    $taskLog = Join-Path $taskRoot 'storage/runtime/postgresql.log'
    Start-Process -FilePath $pgCtlPath -ArgumentList @('-D',$pgDataPath,'-l',$taskLog,'-w','start') -WindowStyle Hidden -Wait
} else { & $pgCtlPath -D $pgDataPath $Action }
