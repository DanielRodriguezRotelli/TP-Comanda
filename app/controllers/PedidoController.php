<?php
require_once './models/Pedido.php';
require_once './models/Producto.php';
require_once './models/Mesa.php';
require_once './controllers/MesaController.php';
require_once './controllers/ProductoController.php';
require_once './controllers/ProductoPedidoController.php';
require_once './controllers/LogController.php';
require_once './interfaces/IApiUsable.php';

class PedidoController extends Pedido implements IApiUsable
{
  public function CargarUno($request, $response, $args)
  {
    $parametros = $request->getParsedBody();
    
    $idMesa = $parametros['idMesa'];
    //$codigoPedido = $parametros['codigoPedido'];
    $idMozo = $parametros['idMozo'];
    $nombreCliente = $parametros['nombreCliente'];
    $productos = $parametros['productos'];  
    $estado = $parametros['estado'];     
    $codigoPedido = PedidoController::AsignarCodigoAlPedido();
    $horarioActual = new DateTime("now");
    
    $auxMesa = Mesa::obtenerMesaPorId($idMesa);
    if (!$auxMesa->disponible) 
    {
      $payload = json_encode(array("mensaje" => "La mesa no esta disponible. Elija otra porfavor."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }

    if ($auxMesa->estado !== "cerrada") 
    {
      $payload = json_encode(array("mensaje" => "La mesa esta ocupada. Elija otra porfavor."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
    
    $pedido = new Pedido();   
    $pedido->idMesa = $idMesa; 
    $pedido->codigoPedido = $codigoPedido;
    $pedido->idMozo = $idMozo; 
    $pedido->nombreCliente = $nombreCliente;   
    $pedido->estado = $estado;  
    $pedido->horarioAlta = $horarioActual;

    $auxproductos = json_decode($productos);
    
    foreach($auxproductos as $producto)
    {  
      $productoComprado = Producto::obtenerProductoPorNombre($producto->nombre);
      
      if($productoComprado)
      {
        Mesa::actualizarEstadoMesa("con cliente esperando pedido", $idMesa);
        ProductoPedidoController::CargarUno($codigoPedido, $productoComprado->sector, $productoComprado->id, $producto->cantidad, "Pendiente");
      }
    }
    $pedido->crearPedido();

    $PedidoDb = $this->TraerUltimoPedidoDesdeDB();

    //$pedido->codigoPedido = PedidoController::AsignarCodigoAlPedido($PedidoDb);

    if (isset($_FILES["fotoMesa"]["tmp_name"])) 
    {
      $auxFotoPedido = $this->tomarFoto($PedidoDb);
      Pedido::asignarFotoAPedido($PedidoDb, $auxFotoPedido);

    }
    LogController::CargarUno($request, "Alta de un pedido");   
    $payload = json_encode(array("mensaje" => "Pedido creado con exito", "codigo" => $pedido->codigoPedido));  
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public static function TraerUltimoPedidoDesdeDB()
  {
    $lista = Pedido::obtenerTodos();
    $ultimoId = 0;
    foreach ($lista as $pedido) 
    {
      if ($ultimoId < $pedido->id) 
      {
        $ultimoId = $pedido->id;
      }
    }
    $auxPedido = Pedido::obtenerPedidoPorId($ultimoId);
    return $auxPedido;
  }

  public static function TraerUltimoIdPedido()
  {
    $lista = Pedido::obtenerTodos();
    $ultimoId = 0;
    foreach ($lista as $pedido) 
    {
      if ($ultimoId < $pedido->id) 
      {
        $ultimoId = $pedido->id;
      }
    }
    return $ultimoId;
  }


  public static function AsignarCodigoAlPedido()
  {
    $idPedido = PedidoController::TraerUltimoIdPedido();
    $codigo = NULL;
    if($idPedido)
    {
      $codigo = "P".$idPedido + 1;  
    } 
    return $codigo;
  }



  public function TraerUno($request, $response, $args)
  {
    $id = $args['id'];
    $pedido = Pedido::obtenerPedidoPorId($id);

    if($pedido)
    {
      $payload = json_encode($pedido);
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');           
    }
    else
    {
      $payload = json_encode(array("mensaje" => "Pedido inválido. Verifique los datos ingresados."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public function TraerTodos($request, $response, $args)
  {
    $lista = Pedido::obtenerTodos();

    //$pedido = PedidoController::TraerUltimoPedidoDesdeDB();
    if($lista)
    {
      $payload = json_encode($lista);
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');
    } 
    else 
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }
    
  public function ModificarUno($request, $response, $args)
  {
    $datos = json_decode(file_get_contents("php://input"), true);
    $pedido = new Pedido();
    $pedido->id=$datos["id"]; 
    $pedido->idMesa=$datos["idMesa"]; 
    $pedido->codigoPedido=$datos["codigoPedido"]; 
    $pedido->idMozo=$datos["idMozo"]; 
    $pedido->nombreCliente=$datos["nombreCliente"];
    $pedido->fotoMesa=$datos["fotoMesa"]; 
    $pedido->horarioPautado=$datos["horarioPautado"];
    $pedido->estado=$datos["estado"]; 

    if(Pedido::modificarPedido($pedido))
    {
      $payload = json_encode(array("mensaje" => "Pedido modificado con exito"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No se pudo modificar el pedido. Verifique los datos ingresados."));  
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public function BorrarUno($request, $response, $args)
  {   
    $id =  $args["id"];
    $pedidoABorrar=Pedido::obtenerPedidoPorId($id);
    if(Pedido::borrarPedido($id))
    {
      ProductoPedido::borrarProductoPedidoPorCodigo($pedidoABorrar->codigoPedido);
      $payload = json_encode(array("mensaje" => "Pedido cancelado con exito"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No se pudo cancelar el pedido. Verifique los datos ingresados."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public static function tomarFoto($PedidoDb)
  {
    $carpetaFotos = ".".DIRECTORY_SEPARATOR."fotosMesas".DIRECTORY_SEPARATOR;
    if(!file_exists($carpetaFotos))
    {
        mkdir($carpetaFotos, 0777, true);
    }

    $nuevoNombre = "Mesa".$PedidoDb->idMesa."-Pedido".$PedidoDb->id;
    $destino = $carpetaFotos . $nuevoNombre . ".jpg";
    if (isset($_FILES["fotoMesa"]["tmp_name"])) 
    {
      $tmpName = $_FILES["fotoMesa"]["tmp_name"];
      if (move_uploaded_file($tmpName, $destino)) 
      {
        return $destino;
      } 
      else 
      {
        return NULL;
      }
    }
    else 
    {
      return NULL;
    }
    
  }


  public static function tomarFotoPosterior($request, $response, $args)
  {
    $parametros = $request->getParsedBody();
    
    $codigoPedido= $parametros["codigoPedido"];
    $pedidoAModificar=Pedido::obtenerPedidoPorCodigo($codigoPedido);

    if (!$pedidoAModificar) 
    {
      $payload = json_encode(array("mensaje" => "El pedido no existe. Revise los datos ingresados."));  
      $response = $response->withStatus(400);
      $response->getBody()->write($payload);
      return $response->withHeader('Content-Type', 'application/json');
    }

    if($pedidoAModificar->fotoMesa == null)
    {
      $auxFotoPedido = self::tomarFoto($pedidoAModificar);
      if (Pedido::asignarFotoAPedido($pedidoAModificar, $auxFotoPedido)) 
      {
        $payload = json_encode(array("mensaje" => "Foto asignada al pedido con exito"));
        $response = $response->withStatus(200);
      } 
      else 
      {
        LogController::CargarUno($request, "Asignar una foto al pedido");
        $payload = json_encode(array("mensaje" => "No se pudo asignar una foto al pedido"));
        $response = $response->withStatus(400);
      }
    }
    else
    {
      $payload = json_encode(array("mensaje" => "El pedido ya posee una foto asignada."));  
      $response = $response->withStatus(400);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }


  public static function calcularTiempoDelPedido()
  {
    $listaDePedidos = Pedido::obtenerTodos();
    if($listaDePedidos)
    {
      foreach($listaDePedidos as $pedido)
      {
        $seccionesComanda = ProductoPedido::obtenerSeccionPorCodigoPedido($pedido->codigoPedido);
        $maximoTiempoPedido = 0;
        $todosTiemposDeterminados=true;
        foreach($seccionesComanda as $seccion)
        {
          if($seccion->horarioPautado !=null )
          {
            if($seccion->horarioPautado > $maximoTiempoPedido)
            {
              $maximoTiempoPedido = $seccion->horarioPautado;
            }
          }
          else
          {
            $todosTiemposDeterminados=false;
            break;
          }
        }

        if($todosTiemposDeterminados && $pedido->estado == "pendiente")
        {
          $pedido->estado = "en preparacion";
          $pedido->horarioPautado = $maximoTiempoPedido;
          Pedido::modificarPedido($pedido);
        }
      }
    }
  }

  public function EmitirInformeTiempoDeDemoraPedido($request, $response, $args)
  {
    PedidoController::calcularTiempoDelPedido();
    
    $codigoMesa = $request->getQueryParams()["codigoMesa"];
    $codigoPedido = $request->getQueryParams()["codigoPedido"];
    
    $mesa = Mesa::obtenerMesaPorCodigo($codigoMesa);
    $pedido = PedidoController::obtenerPedidoPorCodigo($codigoPedido);

    if($mesa && $pedido)
    {
      date_default_timezone_set("America/Argentina/Buenos_Aires");
      $horarioActual = new DateTime("now");
      
      if ($pedido->estado == "entregado") 
      {
        $payload = json_encode(array("mensaje" => "El pedido ya ha sido entregado.")); 
      }
      else 
      {
        if($pedido->horarioPautado != null)
        {
          $horarioPedido = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioPautado);
          if($horarioPedido > $horarioActual)
          {
            $diferenciaEnMinutos = $horarioActual->diff($horarioPedido);
            $minutosRestantes = $diferenciaEnMinutos->days * 24 * 60;
            $minutosRestantes += $diferenciaEnMinutos->h * 60;
            $minutosRestantes += $diferenciaEnMinutos->i; 
            $payload = json_encode(array("Pedido" => "a tiempo", "Tiempo de Entrega" => "En ".$minutosRestantes . " min")); 
          }
          else
          {
            $diferenciaEnMinutos = $horarioPedido->diff($horarioActual);
            $minutosRestantes = $diferenciaEnMinutos->days * 24 * 60;
            $minutosRestantes += $diferenciaEnMinutos->h * 60;
            $minutosRestantes += $diferenciaEnMinutos->i; 
            $payload = json_encode(array("Pedido" => "con demora", "Demora" => $minutosRestantes . " min"));
            $response = $response->withStatus(200);             
          }
        } 
        else 
        {
          $payload = json_encode(array("mensaje" => "Algun producto del pedido aun no ha comenzado a prepararse.")); 
        }
      }
    }
    else
    {
      $payload = json_encode(array("mensaje" => "Codigo de mesa o pedido invalido. Verifique los datos ingresados.")); 
      $response = $response->withStatus(400); 
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirListadoPedidosYTiempoDeDemora($request, $response, $args)
  {
    
    $pedidos = Pedido::obtenerTodos();
    $cantidadDePedidos = count($pedidos);
    $listadoDePedidosYDemoras = array();
    $minutosDeDemora = "";
    $minutosDePreparado = "";
    $demora = "";
    $preparado = "";
   

    if($cantidadDePedidos>0)
    {    
      foreach($pedidos as $pedido)
      {
        $horarioAlta = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioAlta);

        if ($pedido->estado == "cancelado") 
        {
          $demora = "cancelado";
          $preparado = "cancelado";
        }

        if ($pedido->estado == "pendiente") 
        {
          $demora = "pendiente";
          $preparado = "pendiente";
        }

        if ($pedido->estado == "en preparacion") 
        {
          $horarioPautado = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioPautado);
          $horarioEntregado = new DateTime();

          $diferenciaEnMinutos = $horarioEntregado->diff($horarioAlta);
          $minutosDePreparado = $diferenciaEnMinutos->days * 24 * 60;
          $minutosDePreparado += $diferenciaEnMinutos->h * 60;
          $minutosDePreparado += $diferenciaEnMinutos->i;
          $preparado = $minutosDePreparado." min";
          
          if ($horarioEntregado > $horarioPautado) 
          {
            $diferenciaEnMinutos = $horarioEntregado->diff($horarioPautado);
            $minutosDeDemora = $diferenciaEnMinutos->days * 24 * 60;
            $minutosDeDemora += $diferenciaEnMinutos->h * 60;
            $minutosDeDemora += $diferenciaEnMinutos->i;
            $demora = $minutosDeDemora. " min";
          }
          else 
          {
            $demora = "sin demora";
          } 
        }

        if ($pedido->estado == "listo para servir") 
        {
          $horarioPautado = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioPautado);
          $horarioEntregado = new DateTime();

          $diferenciaEnMinutos = $horarioEntregado->diff($horarioAlta);
          $minutosDePreparado = $diferenciaEnMinutos->days * 24 * 60;
          $minutosDePreparado += $diferenciaEnMinutos->h * 60;
          $minutosDePreparado += $diferenciaEnMinutos->i;
          $preparado = $minutosDePreparado." min";
          
          if ($horarioEntregado > $horarioPautado) 
          {
            $diferenciaEnMinutos = $horarioEntregado->diff($horarioPautado);
            $minutosDeDemora = $diferenciaEnMinutos->days * 24 * 60;
            $minutosDeDemora += $diferenciaEnMinutos->h * 60;
            $minutosDeDemora += $diferenciaEnMinutos->i;
            $demora = $minutosDeDemora. " min";
          }
          else 
          {
            $demora = "sin demora";
          } 
        }

        if ($pedido->estado == "entregado") 
        {
          $horarioPautado = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioPautado);
          $horarioEntregado = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioEntregado);
          
          $diferenciaEnMinutos = $horarioEntregado->diff($horarioAlta);
          $minutosDePreparado = $diferenciaEnMinutos->days * 24 * 60;
          $minutosDePreparado += $diferenciaEnMinutos->h * 60;
          $minutosDePreparado += $diferenciaEnMinutos->i;
          $preparado = $minutosDePreparado." min";
          
          if ($horarioEntregado > $horarioPautado) 
          {
            $diferenciaEnMinutos = $horarioEntregado->diff($horarioPautado);
            $minutosDeDemora = $diferenciaEnMinutos->days * 24 * 60;
            $minutosDeDemora += $diferenciaEnMinutos->h * 60;
            $minutosDeDemora += $diferenciaEnMinutos->i;
            $demora = $minutosDeDemora. " min";
          }
          else 
          {
            $demora = "sin demora";
          } 
        }
        
        $listadoDePedidosYDemoras[] = array(
          "codigoPedido" => $pedido->codigoPedido,
          "estado" => $pedido->estado,
          "entrega" => $preparado,
          "demora" => $demora
        );
        
        $response = $response->withStatus(200);
        LogController::CargarUno($request, "Emitir informe de pedidos y demoras de cada uno");  
      }
    }
    else
    {
      $listadoDePedidosYDemoras = array("Mensaje" => "No hay pedidos."); 
      $response = $response->withStatus(400);
    }

    $payload = json_encode($listadoDePedidosYDemoras);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeListosParaServirTodos($request, $response, $args)
  {
    $pedidosListosTodos = Pedido::InformarListosParaServirTodos();

    $cantidadListos = count($pedidosListosTodos);
    if($cantidadListos > 0)
    {     
      LogController::CargarUno($request, "Emitir informe de pedidos listos para servir");     
      $payload = json_encode(array($cantidadListos . " pedidos listos para servir. Detalle: "=> $pedidosListosTodos));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json'); 
    } 
    else 
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos listos para servir"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');   
    }
  }

  public function EmitirInformeMesaMasUsada($request, $response, $args)
  {  
    $mesaMasUsada = Pedido::InformarMesaMasUsada();
    if($mesaMasUsada)
    {
      LogController::CargarUno($request, "Emitir informe de mesa más usada");  
      $payload = json_encode($mesaMasUsada);
      $response = $response->withStatus(200);
    }
    else
    {
      $payload = json_encode(array("No hay mesas abiertas"));
      $response = $response->withStatus(400);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');    
  }

  public function EmitirInformePedidosNoATiempo($request, $response, $args)
  {  
    $pedidos = Pedido::InformarPedidosNoATiempo();
    $cantidadDePedidos = count($pedidos);
    $listadoDePedidosNoAtiempo = array();

    if($cantidadDePedidos>0)
    {    
      foreach($pedidos as $pedido)
      {
        $horarioPedido = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioPautado);
        $horarioEntregado = datetime::createfromformat('Y-m-d H:i:s', $pedido->horarioEntregado);
        $diferenciaEnMinutos = $horarioEntregado->diff($horarioPedido);
        $minutosDeDemora = $diferenciaEnMinutos->days * 24 * 60;
        $minutosDeDemora += $diferenciaEnMinutos->h * 60;
        $minutosDeDemora += $diferenciaEnMinutos->i;   

        $listadoDePedidosNoAtiempo[] = array(
          "Pedido" => $pedido->codigoPedido,
          "Horario pautado" => $pedido->horarioPautado,
          "Horario entregado" => $pedido->horarioEntregado,
          "Minutos de demora" => $minutosDeDemora
        );

        $response = $response->withStatus(200);
        LogController::CargarUno($request, "Emitir informe de pedidos no entregados a tiempo");  
      }
    }
    else
    {
      $listadoDePedidosNoAtiempo = array("Mensaje" => "No hay pedidos."); 
      $response = $response->withStatus(400);
    }

    $payload = json_encode($listadoDePedidosNoAtiempo);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeMesasPorFacturacion($request, $response, $args)
  {  
    $pedidos = Pedido::InformarMesasOrdenadasPorFacturacion();
    $cantidadDePedidos = count($pedidos);
    $mesasConFacturacion = array();

    if($cantidadDePedidos>0)
    {    
      foreach($pedidos as $pedido)
      {
        $mesasConFacturacion[] = array(
          "Código de mesa" => $pedido->codigoMesa,
          "Código de pedido" => $pedido->codigoPedido,
          "Total Facturado" => $pedido->totalFacturado
        );
      }
      $payload = json_encode($mesasConFacturacion);
      $response = $response->withStatus(200);
      LogController::CargarUno($request, "Emitir informes de mesas por monto de facturación");  
    }
    else
    {
      $payload = json_encode(array("Mensaje" => "No hay mesas a informar."));
      $response = $response->withStatus(400);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeFacturadoEntreFechas($request, $response, $args)
  {  
    $idMesa = $request->getQueryParams()["idMesa"];
    $fechaDesde = $request->getQueryParams()["fechaDesde"];
    $fechaHasta = $request->getQueryParams()["fechaHasta"];

    $pedido = Pedido::InformarFacturadoEntreFechasPorMesa($idMesa,$fechaDesde, $fechaHasta);

    $mesaConMayorFacturacion = array();
    /*
    echo"<br>";
    var_dump($pedido);
    echo"<br>";
    */
    if($pedido)
    { 
      $mesaConMayorFacturacion[] = array(
        "Código de mesa" => $pedido->codigoMesa,
        "Desde" => $fechaDesde,
        "Hasta" => $fechaHasta,
        "Total Facturado" => $pedido->facturacion_total
      );

      LogController::CargarUno($request, "Informe de lo facturado por mesa entre determinadas fechas");     
      //$mensaje = "Mesa con mayor facturacion => Id mesa: " . $pedido->idMesa. " Facturacion total desde el " . $fechaDesde . " al " . $fechaHasta . " : $" . $pedido->facturacion_total;
      $payload = json_encode($mesaConMayorFacturacion);
      $response = $response->withStatus(200);
    }
    else
    {
      $response = $response->withStatus(400);
      $payload = json_encode(array("Mensaje" => "La mesa no registro facturacion en el período consultado."));
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformePedidosCancelados($request, $response, $args)
  {
    $pedidosCancelados = Pedido::InformarPedidosCancelados();

    $cantidadPedidosCancelados = count($pedidosCancelados);
    if($cantidadPedidosCancelados > 0)
    {     
      LogController::CargarUno($request, "Emitir informe de pedidos cancelados");     
      $payload = json_encode(array($cantidadPedidosCancelados . " pedidos cancelados. Detalle: "=> $pedidosCancelados));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json'); 
    } 
    else 
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos pedidos cancelados"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');   
    }
  }

  public function EmitirInformeMesaMenosUsada($request, $response, $args)
  {  
    $mesaMenosUsada = Pedido::InformarMesaMenosUsada();
    if($mesaMenosUsada)
    {
      LogController::CargarUno($request, "Emitir informe de mesa menos usada");  
      $payload = json_encode(array("La mesa menos usada es: " => $mesaMenosUsada));
      $response = $response->withStatus(200);
    }
    else
    {
      $payload = json_encode(array("No hay mesas abiertas"));
      $response = $response->withStatus(400);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');    
  }

  public function EmitirInformeMesasFacturacionAcumulada($request, $response, $args)
  {  
    $uri=$_SERVER['REQUEST_URI'];
    $mesaAInformar = array();

    $criterio="";
    $opcion="";

    switch($uri)
    {
      case "/pedidos/InformeMesasMayorFacturacion":
        $criterio="DESC";
        $opcion="mayor";
        break;
      case "/pedidos/InformeMesasMenorFacturacion":
        $criterio="ASC";
        $opcion="menor";
        break;
    }

    $mesa = Pedido::InformarFacturacionAcumuladaMesas($criterio);

    if($mesa)
    {
      $mesaAInformar = array("Mesa con mayor facturación => Id mesa: " . $mesa->idMesa . " Código mesa: " 
      . $mesa->codigoMesa . " Facturación total acumulada " . $mesa->facturacion_total);    
      $response = $response->withStatus(200);
      LogController::CargarUno($request, "Emitir informes de mesas de" . $opcion ."facturación");  
    }
    else
    {
      $mesaAInformar = array("Mensaje" => "No hay pedidos registrados."); 
      $response = $response->withStatus(400);
    }

    $payload = json_encode($mesaAInformar);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }
}

?>