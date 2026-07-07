# Deploy no Railway — maylove-storages

## Branches e ambientes

| Branch Git | Ambiente Railway | Workflow |
|------------|------------------|----------|
| `staging` | staging | `.github/workflows/deploy-staging.yml` |
| `main` | production | `.github/workflows/deploy-production.yml` |

## Secrets GitHub

| Secret | Descrição |
|--------|-----------|
| `RAILWAY_TOKEN_STAGING` | Project token do environment **staging** |
| `RAILWAY_TOKEN_PRODUCTION` | Project token do environment **production** |

## Volume persistente (obrigatório)

Monte um volume Railway em `/var/maylove/storage` e configure:

```
STORAGE_ROOT=/var/maylove/storage
```

Sem volume, uploads e OG images são perdidos a cada redeploy.

## Variáveis críticas

| Variável | Notas |
|----------|-------|
| `STORAGE_ROOT` | caminho do volume |
| `STORAGE_PUBLIC_URL` | URL pública para servir arquivos |
| `SERVICE_TOKEN_SECRET` | igual a `STORAGE_SERVICE_TOKEN_SECRET` na API |
| `DATABASE_URL` | SQLite ou MySQL (metadados) |
| `UPLOAD_MAX_SIZE_MB` | ex.: `50` |

## Healthcheck

`GET /api/v1/health` — definido em `railway.toml`.

## Rede interna

A maylove-api deve acessar este serviço via URL interna Railway (`*.railway.internal`) em `STORAGE_API_URL`.

## Fluxo Git

```
feature/* → PR → staging → PR → main
```
