# Configuración de Colores para Blade en VS Code

## Para diferenciar las etiquetas Blade en el IDE:

### Opción 1: Configuración manual en VS Code

1. Abre VS Code
2. Ve a `File > Preferences > Settings` (o `Ctrl + ,`)
3. Busca `token color customizations`
4. Haz clic en `Edit in settings.json`
5. Agrega la siguiente configuración:

```json
{
  "editor.tokenColorCustomizations": {
    "textMateRules": [
      {
        "scope": "meta.tag.table.blade",
        "settings": {
          "foreground": "#1e40af",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.thead.blade",
        "settings": {
          "foreground": "#7c3aed",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.tbody.blade",
        "settings": {
          "foreground": "#059669",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.tr.blade",
        "settings": {
          "foreground": "#0891b2",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.th.blade",
        "settings": {
          "foreground": "#ea580c",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.td.blade",
        "settings": {
          "foreground": "#be185d",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "keyword.control.blade",
        "settings": {
          "foreground": "#dc2626",
          "fontStyle": "bold"
        }
      }
    ]
  }
}
```

### Opción 2: Extensiones recomendadas

Instala estas extensiones en VS Code:

1. **Blade Formatter**: Ya instalado
2. **Laravel Blade Snippets**: `onecentlin.laravel-blade-snippets`
3. **Laravel Extra Intellisense**: `ambar.blade-laravel-extra-intellisense`

### Colores configurados:

- `<table>`: Azul oscuro (#1e40af)
- `<thead>`: Púrpura (#7c3aed)
- `<tbody>`: Verde (#059669)
- `<tr>`: Cyan (#0891b2)
- `<th>`: Naranja (#ea580c)
- `<td>`: Rosa (#be185d)
- Directivas Blade: Rojo (#dc2626)

### Reiniciar VS Code

Después de configurar, reinicia VS Code para que los cambios tengan efecto.
