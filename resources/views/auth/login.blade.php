<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
     
<div class="logo-login"> <img src="icono_login" alt="icono_login"> </div>        
        
        <div class="Titulo-login">
            <h1>PANIFICADORA AMAZONICA</h1>
            <h2>Sistema de gestion de Panaderia</h2>
        </div>

    <div class="contenedor-login">
        <h1>¡Bienvenido!</h1>
        <h2> ingrese sus datos para continuar </h2>

        <form action="login" method="POST">
            @csrf
            <div class="input-contenedor">
                <i class="fas fa-envelope icon"></i>
                <input type="text" name="username" placeholder="Nombre de usuario">
            </div>

            <div class="input-contenedor">
                <i class="fas fa-key icon"></i>
                <input type="password" name="password" placeholder="Contraseña">
            </div>

            <input type="submit" value="Iniciar Sesion" class="button">
    </div>



    </div>
    
</body>
</html>