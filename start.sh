#!/bin/sh
# Gripp Back-up starten op macOS en Linux:  ./start.sh  (menu)  of bijvoorbeeld  ./start.sh backup --profile=mijnbedrijf
# Zoekt een geschikte Node.js en start daarmee cli.mjs. Staat er geen recente Node op de computer,
# dan wordt de officiële portable versie uit tools/node.lock opgehaald naar .runtime/ en gecontroleerd
# met het vastgelegde SHA-256-controlegetal. Geen installatie en geen beheerdersrechten nodig.
#   GRIPP_PORTABLE_NODE=1   altijd de portable versie gebruiken, ook als er al Node is
set -eu
ROOT=$(cd "$(dirname "$0")" && pwd)
RUNTIME="$ROOT/.runtime"
MIN_MAJOR=18
MIN_MINOR=17

fail() { printf '\n✖  %s\n' "$1" >&2; exit 1; }

# Uitvoer van deze functies is het pad naar node; meldingen gaan naar stderr.
system_node() {
  [ "${GRIPP_PORTABLE_NODE:-}" = 1 ] && return 1
  command -v node >/dev/null 2>&1 || return 1
  v=$(node --version 2>/dev/null | sed 's/^v//')
  major=${v%%.*}; rest=${v#*.}; minor=${rest%%.*}
  case "$major$minor" in ''|*[!0-9]*) return 1 ;; esac
  if [ "$major" -gt "$MIN_MAJOR" ] || { [ "$major" -eq "$MIN_MAJOR" ] && [ "$minor" -ge "$MIN_MINOR" ]; }; then
    command -v node
  else
    return 1
  fi
}

portable_node() {
  lock="$ROOT/tools/node.lock"
  version=$(sed -n 's/^version=//p' "$lock")
  case $(uname -s) in Darwin) os=darwin ;; Linux) os=linux ;; *) fail "Besturingssysteem $(uname -s) wordt niet ondersteund." ;; esac
  case $(uname -m) in x86_64|amd64) arch=x64 ;; arm64|aarch64) arch=arm64 ;; *) fail "Processor $(uname -m) wordt niet ondersteund." ;; esac
  name="node-v$version-$os-$arch"
  dir="$RUNTIME/$name"
  if [ -x "$dir/bin/node" ]; then echo "$dir/bin/node"; return; fi

  expected=$(awk -v f="$name.tar.gz" '$2 == f { print $1 }' "$lock")
  [ -n "$expected" ] || fail "Geen controlegetal voor $name.tar.gz in tools/node.lock."
  mkdir -p "$RUNTIME"
  archive="$RUNTIME/$name.tar.gz"
  url="https://nodejs.org/dist/v$version/$name.tar.gz"
  echo "Node.js $version wordt eenmalig opgehaald van nodejs.org (ca. 50 MB)..." >&2
  if command -v curl >/dev/null 2>&1; then curl -fsSL -o "$archive" "$url" || { rm -f "$archive"; fail "Downloaden mislukt ($url)."; }
  elif command -v wget >/dev/null 2>&1; then wget -q -O "$archive" "$url" || { rm -f "$archive"; fail "Downloaden mislukt ($url)."; }
  else fail "Voor het downloaden is curl of wget nodig."; fi

  if command -v sha256sum >/dev/null 2>&1; then actual=$(sha256sum "$archive" | cut -d' ' -f1)
  else actual=$(shasum -a 256 "$archive" | cut -d' ' -f1); fi
  if [ "$actual" != "$expected" ]; then rm -f "$archive"; fail "Controlegetal klopt niet (verwacht $expected, gekregen $actual). Download verwijderd."; fi

  echo "Uitpakken..." >&2
  tmp="$RUNTIME/uitpakken-$$"
  rm -rf "$tmp"; mkdir -p "$tmp"
  tar -xzf "$archive" -C "$tmp" || fail "Uitpakken mislukt."
  mv "$tmp/$name" "$dir"
  rm -rf "$tmp" "$archive"
  # Oudere versies opruimen
  for old in "$RUNTIME"/node-v*; do [ "$old" = "$dir" ] || rm -rf "$old"; done
  printf 'Node.js %s staat klaar in .runtime/%s\n\n' "$version" "$name" >&2
  echo "$dir/bin/node"
}

node=$(system_node) || node=$(portable_node)
exec "$node" "$ROOT/cli.mjs" "$@"
