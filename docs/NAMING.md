# Convenciones de nombres (MultiAI ChatBot)

Este documento define los prefijos obligatorios para evitar colisiones con temas y otros plugins.

## Resumen

| Capa | Prefijo / patrón | Ejemplo |
|------|------------------|---------|
| Widget público (HTML/CSS/JS DOM) | `maicb-`, `data-maicb-*` | `.maicb-panel`, `[data-maicb="input"]` |
| Contenedor raíz del widget | `#chatbot-plugin-root` o `[data-maicb-root]` | Un `id` único por instancia |
| Variables CSS del widget | `--maicb-*` | `--maicb-primary` |
| Admin (wp-admin) | `chatbot-admin-`, `#chatbot-*` | `.chatbot-admin-history-panel` |
| Variables CSS del admin | `--cb-admin-*` | `--cb-admin-primary` |
| PHP | `Chatbot_*`, funciones/hooks `chatbot_` | `Chatbot_Plugin`, `chatbot_purge_history` |
| Opciones WP | `chatbot_plugin_*` | `chatbot_plugin_settings` |
| Tablas BD | `{prefix}chatbot_*` | `wp_chatbot_conversations` |
| REST API | `chatbot-plugin/v1` | `/wp-json/chatbot-plugin/v1/chat` |
| Assets WP | `chatbot-plugin`, `chatbot-plugin-admin-*` | `wp_enqueue_style( 'chatbot-plugin', ... )` |
| Config JS global | `window.chatbotPluginConfig` | Localizado desde PHP |
| localStorage | `chatbot-plugin-*` | `chatbot-plugin-session-v1` |
| Constantes wp-config | `CHATBOT_*` | `CHATBOT_GEMINI_API_KEY` |

## Prohibido en código nuevo

- Clases del widget con prefijo `cb-` (eliminado; ver CHANGELOG 1.1.0).
- Selectores globales `.maicb-*` sin ancestro `#chatbot-plugin-root` o `#chatbot-style-preview` en `assets/css/chatbot.css`.
- IDs genéricos (`#panel`, `#input`, `#root`).
- Funciones PHP globales sin prefijo `chatbot_`.

## CSS del widget

Todas las reglas de apariencia del chat deben vivir bajo:

```css
#chatbot-plugin-root,
#chatbot-style-preview {
  /* o como ancestro en cada selector */
}
```

La vista previa del admin usa `#chatbot-style-preview` dentro de `#chatbot-preview-viewport`.

## JavaScript del widget

- Inicializar con `document.querySelectorAll('[data-maicb-root]')`.
- Controles críticos: `data-maicb="input"`, `data-maicb="send"`, `data-maicb="panel"`, etc.
- No usar `document.querySelector('.maicb-input')` a nivel documento; siempre desde el `root` de la instancia.

## Filtros WordPress (extensión)

```php
apply_filters( 'chatbot_plugin_root_id', 'chatbot-plugin-root' );
apply_filters( 'chatbot_widget_class_prefix', 'maicb' );
```

## Auditoría

Ejecutar antes de cada release:

```bash
./scripts/check-namespace.sh
```
