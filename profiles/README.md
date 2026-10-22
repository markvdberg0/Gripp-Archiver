# Profielen

Elk Gripp-account krijgt hier een eigen map met een `config.env`:

```
profiles/
├─ mijnbedrijf/config.env
└─ dochterbedrijf/config.env
```

Kopieer `config.env.example` uit de hoofdmap en vul het token en de naam in. Alles in deze map, behalve dit bestand, wordt door git genegeerd. Je tokens komen dus nooit in de repository.

Is er maar één profiel, dan gebruikt de tool dat automatisch. Zijn er meerdere, kies er dan een met `--profile=<naam>`:

```
npm run import -- --profile=dochterbedrijf
```
