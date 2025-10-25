# 🚀 Links - URL Shortener

## 📋 Requisitos del Sistema
- PHP 7.4 o superior
- Extensión JSON habilitada
- Soporte para sesiones
- Permisos de escritura en el servidor

## 🔧 Pasos de Instalación

### 1. **Subir archivos**
Sube todos los archivos a tu hosting via FTP en la carpeta deseada (ej: `public_html/shortener/`)

### 2. **Ejecutar instalador**
Accede a `https://tudominio.com/shortener/install.php`

### 3. **Completar formulario**:
- **Dominio Base**: URL completa de tu sitio (ej: `https://mi.dominio.com` o `https://links.midominio.com`)
- **Ruta Base**: Ruta absoluta donde guardar los datos (ej: `/home/usuario/public_html/shortener`)
- **Usuario Admin**: Nombre de usuario para el administrador
- **Contraseña**: Contraseña segura (mínimo 8 caracteres)

### 4. **Configuración de seguridad**:
- ✅ El instalador crea automáticamente `secure_config/` con protección .htaccess
- ✅ Genera hash seguro de contraseñas usando `PASSWORD_DEFAULT`
- ✅ Configura nombres de sesión únicos

### 5. **Finalizar instalación**:
- ✅ **BORRAR** `install.php` después de la instalación
- ✅ Acceder al panel en `https://tudominio.com/shortener/panel`

## 🌐 Configuración DNS con CNAME

### Para usar un subdominio dedicado (ej: links.tudominio.com):

#### Opción A: CNAME para subdominio
1. **En tu panel de DNS**, crea un registro CNAME:
   ```
   Nombre: links
   Tipo: CNAME
   Valor: tudominio.com
   TTL: 3600 (o automático)
   ```

2. **En cPanel/panel de hosting**:
   - Ve a "Subdominios" o "Dominios"
   - Crea el subdominio `links.tudominio.com`
   - Apúntalo a la carpeta donde instalaste el shortener (ej: `public_html/shortener`)

#### Opción B: Configuración en la instalación
- Durante la instalación, usa como **Dominio Base**: `https://links.tudominio.com`
- Asegúrate de que el subdominio esté configurado en tu hosting para apuntar a la carpeta correcta

### Verificación DNS:
```bash
# Verificar que el CNAME está configurado correctamente
dig links.tudominio.com CNAME

# Debería mostrar:
# links.tudominio.com.   3600    IN    CNAME    tudominio.com.
```

## 🏗️ Estructura del Sistema

```
/home/usuario/public_html/shortener/
├── config.php (generado por instalador)
├── index.php (punto de entrada principal)
├── .htaccess (generado automáticamente)
├── secure_config/ (configuración protegida)
│   ├── secure_config.php
│   └── .htaccess (denega acceso)
├── jsonbackups/ (backups automáticos)
│   └── data.json.backup.20240320123045
└── panel/ (interfaz administrativa)
    ├── index.php (login)
    ├── dashboard.php (panel principal)
    ├── add.php (añadir enlaces)
    ├── edit.php (editar enlaces)
    └── logout.php
```

## 🎯 Características

- ✅ Instalador web sin necesidad de consola
- ✅ Configuración completamente dinámica
- ✅ Seguridad mejorada con archivos de configuración separados
- ✅ Interfaz responsive con Bootstrap
- ✅ Preview de links en redes sociales
- ✅ Sistema de backups automático
- ✅ Estadísticas de clicks
- ✅ Gestión completa de enlaces

## 🔒 Seguridad

- Los archivos de configuración sensibles se guardan en `secure_config/` protegida
- Hash de contraseñas usando el algoritmo más seguro disponible
- Validación de URLs para prevenir XSS
- Nombres de sesión únicos por instalación
- Backups automáticos antes de cada modificación

## 📞 Soporte

Si encuentras problemas durante la instalación:

1. **Verifica requisitos PHP**: Asegúrate de tener PHP 7.4+
2. **Verifica permisos**: Las carpetas necesitan permisos de escritura
3. **Verifica rutas**: Confirma que las rutas absolutas sean correctas
4. **Revisa logs**: Consulta los logs de error de PHP para detalles

### Problemas comunes con DNS:
- **CNAME no resuelve**: Espera 24-48 horas para propagación DNS
- **Error 404**: Verifica que el subdominio apunte a la carpeta correcta
- **SSL no funciona**: Asegúrate de tener certificado SSL para el subdominio


