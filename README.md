# maylove-storages

API independente de armazenamento de arquivos do MayLove.

## Responsabilidade

Único serviço autorizado a persistir bytes (filesystem local no MVP).

- Upload / delete / listagem
- URLs públicas ou protegidas
- Metadados e versionamento
- Organização de diretórios

## Stack

- Symfony 7.2 (API leve)
- Driver MVP: `LocalFilesystemDriver` em `/var/maylove/storage`

## Desenvolvimento

```bash
cp .env.example .env

# Com Docker (recomendado — raiz do monorepo)
docker compose up maylove-storages --build
```

> O arquivo `.env` não é versionado. Use `.env.example` como base.

Health: `GET /api/v1/health`

## API (MVP)

| Rota | Auth | Descrição |
|------|------|-----------|
| `POST /internal/upload-ticket` | `x-maylove-service-token` | Gera ticket de upload |
| `POST /api/v1/files/upload` | `X-Upload-Ticket` | Recebe bytes (multipart) |
| `POST /internal/files/{id}/confirm` | service token | Valida MIME e ativa arquivo |
| `GET /internal/files/{id}/url` | service token | URL assinada com TTL |
| `DELETE /internal/files/{id}` | service token | Remove arquivo |
| `GET /internal/files` | service token | Lista por contexto |
| `GET /serve/{id}?token=` | URL assinada | Serve bytes |
| `GET /platform/{path}` | Público | Assets da plataforma (ex: biblioteca musical) |

## Biblioteca musical

15 faixas em `platform/music/` para a maylove-api (`GET /api/v1/music-tracks`).

```bash
# Gera placeholders MP3 (dev)
php bin/console app:seed-music-library

# Usa MP3s reais de assets/music-source/
php bin/console app:seed-music-library --source-dir=assets/music-source --force
```

No Docker, o entrypoint roda o seed automaticamente na primeira subida.

`SERVICE_TOKEN_SECRET` deve ser igual ao `STORAGE_SERVICE_TOKEN_SECRET` da maylove-api.

## Documentação

[specs/06-upload-midias/](../specs/06-upload-midias/)  
[specs/14-arquitetura/03-maylove-storages.md](../specs/14-arquitetura/03-maylove-storages.md)

## MVP

Driver: `LocalFilesystemDriver` em volume `/var/maylove/storage`  

**Produção (Railway):** anexe um volume em `/var/maylove/storage` e defina `STORAGE_ROOT=/var/maylove/storage`. Sem volume, arquivos são perdidos a cada redeploy. Ver [docs/DEPLOY-RAILWAY.md](docs/DEPLOY-RAILWAY.md).
**Sem** AWS S3 / GCS no MVP.
