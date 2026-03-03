# AGENTS.md

## 目的
このリポジトリは Laravel を中心とした単一アプリ構成を対象とします。
- バックエンド: PHP (Laravel)
- 実行基盤: Docker Compose / Cloud Run

## 作業方針
- アプリ本体は `backend/` に集約する。
- インフラ設定は `infra/` と `docker-compose.yml` にコードとして残す。
- 機密情報（`.env`、秘密鍵、資格情報）はコミットしない。

## バックエンド規約
- フレームワークは Laravel 12 を標準とする。
- スキーマ変更は必ず migration で管理する。
- 中〜高複雑度の業務ロジックは Service / Repository で分離する。
- 書き込み系エンドポイントは Request Validation を必須とする。
- API レスポンス形式とエラーコードを統一する。

## フロントエンド規約
- 画面は Blade を基本とし、フロント資産は `backend/resources` 配下で管理する。
- CSS は `backend/resources/css/app.css` をエントリとし、Tailwind CSS を利用する。
- JS は `backend/resources/js/app.js` をエントリとし、Vite 経由で読み込む。
- レイアウトは `backend/resources/views/layouts`、ページは `backend/resources/views/pages` に配置する。
- 新規フロント技術を追加する場合は、採用理由と運用方針を README に追記する。

## Docker / DevOps
- ローカル環境は `docker compose up -d --build` で起動できる状態を維持する。
- ポート変更時は README を必ず更新する。
- サービス追加時は目的とヘルスチェック方針を明記する。

## 品質基準
- バックエンド: PHPUnit / Pest で重要フローをテストする。
- マージ前に lint / test を実行する。

## PR の最低要件
1. 実装コード
2. 設定・migration の更新
3. 最低限のテスト
4. セットアップや挙動変更がある場合の README 更新
