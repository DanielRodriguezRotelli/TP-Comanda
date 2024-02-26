<?php
require_once './models/Log.php';
require_once './controllers/UsuarioController.php';
require_once './models/Usuario.php';
require_once './models/AutentificadorJWT.php';

class LogController extends Log
{

  public static function CargarLogin($usuario, $operacion)
  {
    if($usuario)
    {
      $log = new Log();
      $log->idUsuario = $usuario->id;
      $log->operacion = $operacion;
      $log->crearLog();
    } 
    else 
    {
      echo "usuario inválido";
    }
  } 


  public static function CargarUno($request, $operacion)
  {
    $header = $request->getHeaderLine('Authorization'); 
    $token = trim(explode("Bearer", $header)[1]);
    $data = AutentificadorJWT::ObtenerData($token); 
    $usuario = UsuarioController::obtenerUsuario($data->nombre);

    if($usuario)
    {
      $log = new Log();
      $log->idUsuario = $usuario->id;
      $log->operacion = $operacion;
      $log->crearLog();
    } 
    else 
    {
      echo "usuario inválido";
    }
  } 

  public function EmitirInformeOperacionesPorSector($request, $response, $args)
  {
    $listaDeOperaciones = Log::InformarOperacionesPorSector();

    $informeDeOperaciones = array();
    $cantidadDeOperaciones = 0;

    if(count($listaDeOperaciones)> 0)
    {
      foreach($listaDeOperaciones as $operacion)
      {

        $informeDeOperaciones[] = array(
          "Sector" => $operacion->perfil,
          "Cantidad Operaciones" => $operacion->cantidad_operaciones
        );

        //$mensaje = "Sector: " . $operacion->perfil. " - Cantidad de operaciones: ". $operacion->cantidad_operaciones;
        $cantidadDeOperaciones=$cantidadDeOperaciones+$operacion->cantidad_operaciones;
        //array_push($informeDeOperaciones, $mensaje);
      }

      //$mensajeTotal = "Total de operaciones de todos los sectores: " . $cantidadDeOperaciones;
      //array_push($informeDeOperaciones, $mensajeTotal);
      //LogController::CargarUno($request, "Emitir informe de operaciones por sector");
      $payload = json_encode(array("Total de operaciones" =>$cantidadDeOperaciones, "Detalle"=>$informeDeOperaciones));
    }
    else
    {
      $informeDeOperaciones = array("Mensaje" => "No se registraron operaciones.");
      $payload = json_encode($informeDeOperaciones);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeOperacionesPorEmpleadoPorSector($request, $response, $args)
  {
    $listaDeOperaciones = Log::InformarOperacionesPorEmpleadoPorSector();
    /*
    echo"<br>";
    var_dump($listaDeOperaciones);
    echo"<br>";
    */
    $informeDeOperacionesMozo = array();
    $informeDeOperacionesBartender= array();
    $informeDeOperacionesCervecero = array();
    $informeDeOperacionesCocinero = array();
    $informeDeOperacionesSocio = array();
    $cantidadDeOperaciones = 0;

    if(count($listaDeOperaciones)> 0)
    {
      foreach($listaDeOperaciones as $operacion)
      {
        switch ($operacion->perfil) 
        {
          case 'mozo':
            $informeDeOperacionesMozo[] = array(
              "Id Usuario" => $operacion->idUsuario,
              "Nombre" => $operacion->nombre,
              "Cantidad Operaciones" => $operacion->cantidad_operaciones
            );
            break;

          case 'bartender':
            $informeDeOperacionesBartender[] = array(
              "Id Usuario" => $operacion->idUsuario,
              "Nombre" => $operacion->nombre,
              "Cantidad Operaciones" => $operacion->cantidad_operaciones
            );
            break;

          case 'cervecero':
            $informeDeOperacionesCervecero[] = array(
              "Id Usuario" => $operacion->idUsuario,
              "Nombre" => $operacion->nombre,
              "Cantidad Operaciones" => $operacion->cantidad_operaciones
            );
            break;

          case 'cocinero':
            $informeDeOperacionesCocinero[] = array(
              "Id Usuario" => $operacion->idUsuario,
              "Nombre" => $operacion->nombre,
              "Cantidad Operaciones" => $operacion->cantidad_operaciones
            );
            break;

          case 'socio':
            $informeDeOperacionesSocio[] = array(
              "Id Usuario" => $operacion->idUsuario,
              "Nombre" => $operacion->nombre,
              "Cantidad Operaciones" => $operacion->cantidad_operaciones
            );
            break;
        }

        $cantidadDeOperaciones=$cantidadDeOperaciones+$operacion->cantidad_operaciones;
      }
      
      $payload = json_encode(array("Total operaciones" =>$cantidadDeOperaciones, "Mozo" => $informeDeOperacionesMozo,
                                    "Bartender" => $informeDeOperacionesBartender,"Cervecero" => $informeDeOperacionesCervecero,
                                    "Cocinero" => $informeDeOperacionesCocinero, "Socio" => $informeDeOperacionesSocio));
      LogController::CargarUno($request, "Emitir informe de operaciones por empleados agrupados por sector");
    }
    else
    {
      $payload = json_encode(array("Mensaje" => "No se registraron operaciones."));
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeDeLoginPorEmpleado($request, $response, $args)
  {
    $idEmpleado = $request->getQueryParams()["idEmpleado"];
    //$idEmpleado = $args["idEmpleado"];
    $auxEmpleado = Usuario::obtenerUsuarioPorId($idEmpleado);
    $listaDeLogins = Log::InformarLoginsPorEmpleado($idEmpleado);
    $informeDeLogins = array();

    if(count($listaDeLogins)> 0)
    { 
      foreach($listaDeLogins as $login)
      {
        $fecha = substr($login->fecha, 0, 10); 
        $horario = substr($login->fecha, 11); 
        $informeDeLogins[] = array(
          "Fecha" => $fecha,
          "Horario" => $horario
        );
      }

      LogController::CargarUno($request, "Emitir informe de logins por empleado");
      $payload = json_encode(array("Id Usuario" => $idEmpleado, "Nombre Usuario" => $auxEmpleado->nombre, "Logins" => $informeDeLogins));
    }
    else
    {
      $payload = json_encode(array("Mensaje" => "No se registraron logins para este empleado."));
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeOperacionesPorEmpleado($request, $response, $args)
  {
    $idEmpleado = $args["idEmpleado"];
    $listaDeOperaciones = Log::InformarOperacionesPorEmpleado($idEmpleado);
    $informeDeOperaciones = array();
    $cantidadDeOperaciones = count($listaDeOperaciones);

    if($cantidadDeOperaciones>0)
    {
      foreach($listaDeOperaciones as $operacion)
      {
        $mensaje = "Id empleado: " . $operacion->idUsuario. "- Nombre: " . $operacion->nombre . " - Fecha: ". $operacion->fecha . " - Operación: ". $operacion->operacion;
        array_push($informeDeOperaciones, $mensaje);
      }

      $mensajeTotal = "Total de operaciones del usuario " . $operacion->nombre  . " : " . $cantidadDeOperaciones;
      array_push($informeDeOperaciones, $mensajeTotal);
      LogController::CargarUno($request, "Emitir informe de operaciones por empleado");
    }
    else
    {
      $informeDeOperaciones = array("Mensaje" => "No se registraron operaciones para este usuario.");
    }

    $payload = json_encode($informeDeOperaciones);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

}

?>