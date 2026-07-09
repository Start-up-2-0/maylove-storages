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

Sem volume, **todos os uploads são perdidos a cada redeploy** da imagem Docker.

### Configurar no Railway (uma vez por ambiente)

1. Abra o serviço **maylove-storages** → **Settings** → **Volumes** → **Add Volume**
2. **Mount path:** `/var/maylove/storage`
3. Variável de ambiente:
   ```
   STORAGE_ROOT=/var/maylove/storage
   ```

O Railway injeta `RAILWAY_VOLUME_MOUNT_PATH` automaticamente. O bootstrap do container usa esse caminho e **falha o deploy** se estiver em produção no Railway sem volume anexado.

### Via CLI

```bash
railway link
railway volume add -m /var/maylove/storage
```

Confirme com `railway volume list`. O mount path deve ser exatamente `/var/maylove/storage` (igual ao `STORAGE_ROOT`).

### Como validar

Após o deploy, `GET /api/v1/health` deve retornar:

```json
{
  "checks": {
    "storage_root": "ok",
    "storage_persistent": "ok",
    "database": "ok"
  }
}
```

Se `storage_persistent` for `error`, o volume não está anexado.

**Nota:** volumes não são montados durante o pre-deploy; migrations de banco rodam sem acesso ao disco. O storage é validado apenas na subida do container (`start-web.sh`).

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
