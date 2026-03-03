# Pyxis FW (Laravel + Docker)

## 構成
- バックエンド: Laravel 12 (PHP 8.3, FPM, Nginx)
- インフラ: Docker Compose / Cloud Run

## ディレクトリ
- `backend/`: Laravel アプリ本体
- `infra/`: Dockerfile / Nginx 設定

## 1) サービス起動
```bash
docker compose up -d --build
```

## 2) 依存関係のインストール (初回)
```bash
docker compose exec backend-app composer install
```

## 3) Laravel 環境設定とキー生成（DB 利用時のみ migration）
```bash
cp backend/.env.example backend/.env
docker compose exec backend-app php artisan key:generate
# DB を使う段階で実行:
# docker compose exec backend-app php artisan migrate
```

## アクセス先
- App: http://localhost:8080

## 補足
- 現在の Docker 構成には DB/Redis を含めていません。必要になった時点で追加してください。
- 現時点では `php artisan migrate` は実行しないでください（DB 導入後に実行）。
- 本番運用では compose を分割し、開発用マウントを外してください。

## Cloud Run（単一サービス）
このリポジトリには Cloud Run 用の単一コンテナ構成を追加しています。

- Dockerfile: `infra/cloudrun/Dockerfile`
- 起動スクリプト: `infra/cloudrun/start.sh`
- Nginx 設定: `infra/cloudrun/nginx.conf.template`
- Cloud Build 設定: `cloudbuild.yaml`

### デプロイの流れ（例）
```bash
gcloud builds submit --config cloudbuild.yaml \
  --substitutions=_REGION=asia-northeast1,_REPO=pyxis-repo,_IMAGE=pyxis-fw,_TAG=latest,_SERVICE=pyxis-fw
```

### ルーティング
- `/` は Laravel にルーティング
- 静的ファイルは `public/` から配信

## フロント資産のビルド方針
- フロント資産は `backend` 内の Vite でビルドします（`npm run build`）。
- 出力ファイル名は固定化設定にしてあり、毎回の hash 変動による参照不整合リスクを下げています。
- 動的 import を多用すると分割チャンク管理が複雑になるため、必要時のみ使用してください。

## フロントエンド開発（Blade + Tailwind + Vite）

### 現在の基本方針
- 画面は Blade を基本とする（SSR）。
- スタイルは Tailwind CSS を利用する。
- 資産バンドルは Vite を利用する。

### ファイル配置
- レイアウト: `backend/resources/views/layouts/`
- ページ: `backend/resources/views/pages/`
- Blade コンポーネント: `backend/resources/views/components/`
- CSS エントリ: `backend/resources/css/app.css`
- JS エントリ: `backend/resources/js/app.js`

### 開発時の実行
```bash
cd backend
npm run dev
```

### 本番用ビルド
```bash
cd backend
npm run build
```

### 注意点
- `npm run dev` は開発サーバーでのホットリロード用です。
- `public/build` の本番用成果物を更新したい場合は `npm run build` を実行してください。

