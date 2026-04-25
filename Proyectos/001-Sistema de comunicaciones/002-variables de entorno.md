# Variables de entorno en Windows

Cuando creamos variables de entorno lo que hacemos es proteger la información, sacándola de nuestro código y metiéndola en el sistema.

## Desde la interfaz de Windows

Buscamos:

`variables de entorno`

Abrimos:

`Editar las variables de entorno del sistema`

Entramos en:

`Variables de entorno`

En **Variables de usuario** creamos:

```text
MI_CORREO_KITCHERRY = pieroolivaresdev@gmail.com
MI_CONTRASENA_CORREO_KITCHERRY = XXXXXX
MI_SERVIDORIMAP_CORREO_KITCHERRY = imap.gmail.com
```

Cerramos y volvemos a abrir la terminal.

Comprobamos las variables:

### En CMD

```bat
echo %MI_CORREO_KITCHERRY%
echo %MI_CONTRASENA_CORREO_KITCHERRY%
echo %MI_SERVIDORIMAP_CORREO_KITCHERRY%
```

### En PowerShell

```powershell
echo $env:MI_CORREO_KITCHERRY
echo $env:MI_CONTRASENA_CORREO_KITCHERRY
echo $env:MI_SERVIDORIMAP_CORREO_KITCHERRY
```

## Desde terminal en Windows

### En CMD

```bat
setx MI_CORREO_KITCHERRY "pieroolivaresdev@gmail.com"
setx MI_CONTRASENA_CORREO_KITCHERRY "XXXXXX"
setx MI_SERVIDORIMAP_CORREO_KITCHERRY "imap.gmail.com"
```

### En PowerShell

```powershell
[System.Environment]::SetEnvironmentVariable("MI_CORREO_KITCHERRY", "pieroolivaresdev@gmail.com", "User")
[System.Environment]::SetEnvironmentVariable("MI_CONTRASENA_CORREO_KITCHERRY", "XXXXXX", "User")
[System.Environment]::SetEnvironmentVariable("MI_SERVIDORIMAP_CORREO_KITCHERRY", "imap.gmail.com", "User")
```

Después cerramos y abrimos de nuevo la terminal.
