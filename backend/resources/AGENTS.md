# AGENTS.md

## 目的
`backend/resources` 配下のフロントエンド実装方針を明確化し、運用を統一する。

## フロントエンド実装ルール
- デフォルトは Blade + Tailwind とし、初期段階から Vue SPA を導入しない。
- UI のインタラクションは Alpine.js を優先し、複雑なフォーム要件がある場合のみ Livewire を検討する。
- 非必要なフロントエンド依存パッケージは追加しない。
- Cloud Run 想定では SSR を優先し、クライアントサイド状態管理の複雑化を避ける。
- 変更時は既存ディレクトリ構成（`layouts` / `pages` / `components`）の再利用を優先する。
