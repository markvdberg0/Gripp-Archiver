# Startscript voor Windows (wordt aangeroepen door Start-Windows.cmd).
# Zoekt een geschikte Node.js en start daarmee cli.mjs. Staat er geen recente Node op de computer,
# dan wordt de officiele portable versie uit tools\node.lock opgehaald naar .runtime\ en gecontroleerd
# met het vastgelegde SHA-256-controlegetal. Geen installatie en geen beheerdersrechten nodig.
#   GRIPP_PORTABLE_NODE=1   altijd de portable versie gebruiken, ook als er al Node is
# Let op: alleen ASCII in dit bestand (Windows PowerShell 5.1 leest het anders verkeerd).
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'   # anders is Invoke-WebRequest in PowerShell 5.1 erg traag
$Root = Split-Path -Parent $PSScriptRoot
$Runtime = Join-Path $Root '.runtime'
$MinVersion = [version]'18.17.0'

function Fail([string] $Message) {
  Write-Host ''
  Write-Host "X  $Message" -ForegroundColor Red
  exit 1
}

function Find-SystemNode {
  if ($env:GRIPP_PORTABLE_NODE -eq '1') { return $null }
  $cmd = Get-Command node.exe -ErrorAction SilentlyContinue
  if (-not $cmd) { return $null }
  try {
    $v = [version]((& $cmd.Source --version) -replace '^v', '')
    if ($v -ge $MinVersion) { return $cmd.Source }
  } catch {}
  return $null
}

function Get-PortableNode {
  $lock = Get-Content (Join-Path $PSScriptRoot 'node.lock')
  $version = ($lock | Where-Object { $_ -like 'version=*' }) -replace '^version=', ''
  $arch = $env:PROCESSOR_ARCHITECTURE
  if ($env:PROCESSOR_ARCHITEW6432) { $arch = $env:PROCESSOR_ARCHITEW6432 }   # 32-bits PowerShell op 64-bits Windows
  switch ($arch) {
    'AMD64' { $a = 'x64' }
    'ARM64' { $a = 'arm64' }
    default { Fail "Processor $arch wordt niet ondersteund." }
  }
  $name = "node-v$version-win-$a"
  $dir = Join-Path $Runtime $name
  $exe = Join-Path $dir 'node.exe'
  if (Test-Path $exe) { return $exe }

  $line = $lock | Where-Object { $_ -match ('^[0-9a-f]{64}  ' + [regex]::Escape("$name.zip") + '$') }
  if (-not $line) { Fail "Geen controlegetal voor $name.zip in tools\node.lock." }
  $expected = $line.Substring(0, 64)

  New-Item -ItemType Directory -Force $Runtime | Out-Null
  $zip = Join-Path $Runtime "$name.zip"
  Write-Host "Node.js $version wordt eenmalig opgehaald van nodejs.org (ca. 35 MB)..."
  [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
  try {
    Invoke-WebRequest -UseBasicParsing -Uri "https://nodejs.org/dist/v$version/$name.zip" -OutFile $zip
  } catch {
    Remove-Item $zip -Force -ErrorAction SilentlyContinue
    Fail "Downloaden mislukt: $($_.Exception.Message)"
  }
  $actual = (Get-FileHash -Algorithm SHA256 $zip).Hash.ToLower()
  if ($actual -ne $expected) {
    Remove-Item $zip -Force
    Fail "Controlegetal klopt niet (verwacht $expected, gekregen $actual). Download verwijderd."
  }

  Write-Host 'Uitpakken...'
  $tmp = Join-Path $Runtime "uitpakken-$PID"
  New-Item -ItemType Directory -Force $tmp | Out-Null
  $tar = Join-Path $env:SystemRoot 'System32\tar.exe'   # Windows 10 1803+; veel sneller dan Expand-Archive
  if (Test-Path $tar) { & $tar -xf $zip -C $tmp; if ($LASTEXITCODE -ne 0) { Fail 'Uitpakken mislukt.' } }
  else { Expand-Archive -Path $zip -DestinationPath $tmp -Force }
  Move-Item (Join-Path $tmp $name) $dir
  Remove-Item $tmp, $zip -Recurse -Force
  # Oudere versies opruimen
  Get-ChildItem $Runtime -Directory -Filter 'node-v*' | Where-Object { $_.Name -ne $name } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
  Write-Host "Node.js $version staat klaar in .runtime\$name"
  Write-Host ''
  return $exe
}

$node = Find-SystemNode
if (-not $node) { $node = Get-PortableNode }
& $node (Join-Path $Root 'cli.mjs') @args
exit $LASTEXITCODE
