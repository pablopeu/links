# 🚀 Instalación URL Shortener

## 📋 Requisitos del Sistema
- PHP 7.4 o superior
- Extension JSON habilitada
- Soporte para sesiones
- Permisos de escritura en el servidor

## 🔧 Pasos de Instalación

1. **Subir archivos**: Sube todos los archivos a tu hosting via FTP
2. **Ejecutar instalador**: Accede a `https://tudominio.com/install.php`
3. **Completar formulario**:
   - **Dominio Base**: URL completa de tu sitio (ej: https://mi.dominio.com)
   - **Ruta Base**: Ruta absoluta donde guardar los datos (ej: /home/usuario/public_html/mi)
   - **Carpeta Segura**: Fuera de public_html para mayor seguridad (ej: /home/usuario/secure_config)
   - **Usuario Admin**: Nombre de usuario para el administrador
   - **Contraseña**: Contraseña segura (mínimo 8 caracteres)

4. **Configuración de seguridad**:
   - ✅ El instalador crea automáticamente `secure_config.php` fuera de public_html
   - ✅ Genera hash seguro de contraseñas usando `PASSWORD_DEFAULT`
   - ✅ Configura nombres de sesión únicos

5. **Finalizar instalación**:
   - ✅ **BORRAR** `install.php` después de la instalación
   - ✅ Configurar DNS si es necesario
   - ✅ Acceder al panel en `https://tudominio.com/panel`

## 🎯 Características

- ✅ Instalador web sin necesidad de consola
- ✅ Configuración completamente dinámica
- ✅ Seguridad mejorada con archivos de configuración separados
- ✅ Interfaz responsive con Bootstrap
- ✅ Sistema de backups automático
- ✅ Estadísticas de clicks
- ✅ Gestión completa de enlaces

## 🔒 Seguridad

- Los archivos de configuración sensibles se guardan fuera de public_html
- Hash de contraseñas usando el algoritmo más seguro disponible
- Validación de URLs para prevenir XSS
- Nombres de sesión únicos por instalación

## 📞 Soporte

Si encuentras problemas durante la instalación:
1. Verifica que todos los requisitos de PHP estén cumplidos
2. Asegúrate de que las rutas tengan permisos de escritura
3. Revisa los logs de error de PHP

¡Tu URL shortener estará listo en minutos! 🎉