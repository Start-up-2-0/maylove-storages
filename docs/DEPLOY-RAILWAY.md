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
| `DEFAULT_URI` | URL pública do serviço (ex.: `https://maylove-storages-staging.up.railway.app`) |
| `SERVICE_TOKEN_SECRET` | igual a `STORAGE_SERVICE_TOKEN_SECRET` na API (mín. 32 caracteres) |
| `DATABASE_URL` | MySQL 8 (banco `maylove_storages`, separado da API) |
| `UPLOAD_MAX_SIZE_MB` | ex.: `50` |
| `CORS_ALLOW_ORIGIN` | regex do domínio do app (ex.: `^https://maylove-app-staging\.up\.railway\.app$`) |

**Importante:** no Railway, defina valores **sem aspas** (`prod`, não `"prod"`). Aspas no valor impedem as migrations e a tabela `files` não é criada.

## Migrations

Pendentes são aplicadas no **pre-deploy** e em background no **start** (`docker/migrate.sh`), depois que o PHP já está escutando na `PORT`.

```
DATABASE_URL=${{MySQL.MYSQL_PRIVATE_URL}}
```

## Healthcheck

- Railway (liveness): `GET /api/v1/health/live`
- Monitoramento: `GET /api/v1/health`

## Porta

O serviço escuta em `0.0.0.0:$PORT`. Confira o target port em Settings → Networking. Ver [Application Failed to Respond](https://docs.railway.com/networking/troubleshooting/application-failed-to-respond).

## Rede interna

A maylove-api deve acessar este serviço via URL interna Railway (`*.railway.internal`) em `STORAGE_API_URL`.

## Fluxo Git

```
feature/* → PR → staging → PR → main
```
