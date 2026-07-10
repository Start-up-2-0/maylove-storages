# Fonte da biblioteca musical

Coloque aqui os MP3 reais da biblioteca MayLov, **um arquivo por slug**:

```
acorde-do-coracao.mp3
eterno-amor.mp3
sussurro-de-amor.mp3
...
```

Lista completa de slugs: `src/Application/Platform/MusicLibraryCatalog.php`

## Aplicar no storage

```bash
# Placeholders silenciosos (dev/MVP)
php bin/console app:seed-music-library

# Substituir por faixas reais
php bin/console app:seed-music-library --source-dir=assets/music-source --force
```

Os arquivos ficam em `{STORAGE_ROOT}/platform/music/` e são servidos em:

`GET /platform/music/{slug}.mp3`
