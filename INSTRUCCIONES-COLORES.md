# 🎨 INSTRUCCIONES PARA COLORES DE ETIQUETAS BLADE

## ⚠️ PROBLEMA: Las etiquetas no se diferencian en VS Code

## 🔧 SOLUCIÓN MANUAL (Requiere acción del usuario)

### Paso 1: Abrir configuración de VS Code
1. Abre VS Code
2. Presiona `Ctrl + ,` (o `File > Preferences > Settings`)
3. En el buscador superior, escribe: `token color customizations`

### Paso 2: Editar settings.json
1. Haz clic en `Edit in settings.json` (aparece en la búsqueda)
2. Se abrirá un archivo JSON
3. Copia y pega esta configuración completa:

```json
{
  "editor.tokenColorCustomizations": {
    "textMateRules": [
      {
        "scope": "meta.tag.table.html",
        "settings": {
          "foreground": "#1e40af",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.thead.html",
        "settings": {
          "foreground": "#7c3aed",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.tbody.html",
        "settings": {
          "foreground": "#059669",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.tr.html",
        "settings": {
          "foreground": "#0891b2",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.th.html",
        "settings": {
          "foreground": "#ea580c",
          "fontStyle": "bold"
        }
      },
      {
        "scope": "meta.tag.td.html",
        "settings": {
          "foreground": "#be185d",
          "fontStyle": "bold"
        }
      }
    ]
  },
  "files.associations": {
    "*.blade.php": "blade"
  },
  "emmet.includeLanguages": {
    "blade": "html"
  }
}
```

### Paso 3: Guardar y Reiniciar
1. Guarda el archivo (`Ctrl + S`)
2. Cierra VS Code completamente
3. Vuelve a abrir VS Code
4. Abre el archivo `porduct_download_excel.blade.php`

## 🎯 RESULTADO ESPERADO

Después de reiniciar VS Code, deberías ver:

- `<table>` → **AZUL OSCURO** 🔵
- `<thead>` → **PÚRPURA** 🟣  
- `<tbody>` → **VERDE** 🟢
- `<tr>` → **CYAN** 🟦
- `<th>` → **NARANJA** 🟠
- `<td>` → **ROSA** 🩷

## 🔍 VERIFICACIÓN

Si no funciona:

1. Verifica que copiaste TODO el JSON correctamente
2. Reinicia VS Code completamente (no solo la ventana)
3. Verifica que tienes la extensión `onecentlin.laravel-blade` instalada

## 📋 EXTENSIONES NECESARIAS

Ejecuta estos comandos en la terminal:

```bash
code --install-extension onecentlin.laravel-blade
code --install-extension shufo.vscode-blade-formatter
```

## ⚡ ALTERNATIVA RÁPIDA

Si lo anterior no funciona, prueba este tema:

1. Ve a `File > Preferences > Color Theme`
2. Busca `Blade`
3. Selecciona cualquier tema que contenga "Blade"

---

**IMPORTANTE**: Esta configuración debe aplicarse MANUALMENTE en VS Code. Los archivos que creé son solo referencia.
