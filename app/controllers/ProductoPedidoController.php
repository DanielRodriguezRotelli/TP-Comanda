<?php
require_once './models/ProductoPedido.php';
require_once './controllers/LogController.php';
require_once './models/Pedido.php';

class ProductoPedidoController extends ProductoPedido
{
  public static function CargarUno($codigoPedido, $perfil, $idProducto, $cantidad, $estado)
  {        
    $productoPedido = new ProductoPedido();
    $productoPedido->codigoPedido = $codigoPedido;
    $productoPedido->perfil = $perfil;
    $productoPedido->idProducto = $idProducto;      
    $productoPedido->cantidad = $cantidad;      
    $productoPedido->estado = $estado;        
    
    $productoPedido->crearProductoPedido();
  }

  public function TraerUno($request, $response, $args)
  {
    $codigoPedido = $args['codigoPedido'];
    $pedido = Pedido::obtenerPedidoPorCodigo($codigoPedido);

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
      $response = $response->withStatus(400);
      $response->getBody()->write($payload);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public function TraerTodos($request, $response, $args)
  {
    $lista = Pedido::obtenerTodos();
    if($lista)
    {
      $payload = json_encode(array("Lista de productos por pedidos" => $lista));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos registrados."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }      
  }
  
  public function ModificarUno($request, $response, $args)
  {
    $parametros = $request->getParsedBody();
    $idPedido = $args["id"];
    $pedido = Pedido::obtenerPedidoPorId($idPedido);

    if($pedido)
    {
      $pedido->estado = $parametros['estado'];

      if(Pedido::modificarPedido($pedido))
      {
        $payload = json_encode(array("mensaje" => "Pedido modificado con exito"));
        $response->getBody()->write($payload);
        $response = $response->withStatus(200);
        return $response->withHeader('Content-Type', 'application/json');
      }
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No se pudo modificar el pedido. Intente nuevamente"));  
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public function BorrarUno($request, $response, $args)
  {   
    $idPedido =  $args["id"];

    if(Pedido::borrarPedido($idPedido)){
      $payload = json_encode(array("mensaje" => "Pedido borrado con exito"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json');
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No se pudo borrar el producto. Intente nuevamente."));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');
    }
  }

  public function EmitirInformePendientesPorPerfil($request, $response, $args)
  {
    
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $perfil="";
  
    switch($uri)
    {
      case "/comanda/app/ProductoPedido/InformePendientesBartender":
        $perfil="bartender";
        break;
      case "/comanda/app/ProductoPedido/InformePendientesCervecero":
        $perfil="cervecero";
        break;
      case "/comanda/app/ProductoPedido/InformePendientesCocinero":
        $perfil="cocinero";
        break;
    }

    $pedidosPendientes = ProductoPedido::InformarPendientesPorPerfil($perfil);
    
    $cantidadPendientes = count($pedidosPendientes);
    if($cantidadPendientes > 0)
    {         
      LogController::CargarUno($request, "Emitir informe de pedidos pendientes por perfil");  

      $pedidos = array();
      foreach ($pedidosPendientes as $pedido) 
      {
          $pedidos[] = array(
            "id" => $pedido->id,
            "codigoPedido" => $pedido->codigoPedido,
            "idProducto" => $pedido->idProducto,
            "cantidad" => $pedido->cantidad
          );
      }

      $payload = json_encode(array("Pedidos pendiente: " => $cantidadPendientes, "Detalle: "=> $pedidos));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json'); 
    } 
    else 
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos pendientes"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');   
    }
  }

  public function TomaDePedidoPorPerfil($request, $response, $args)
  {
    $perfil="";

    $uri=$_SERVER['REQUEST_URI'];
  
    switch($uri)
    {
      case "/comanda/app/ProductoPedido/TomaDePedidoBartender":
        $perfil="bartender";
        break;
      case "/comanda/app/ProductoPedido/TomaDePedidoCervecero":
        $perfil="cervecero";
        break;
      case "/comanda/app/ProductoPedido/TomaDePedidoCocinero":
        $perfil="cocinero";
        break;
    }

    $parametros = $request->getParsedBody();
    $estado = isset($parametros['estado']) ? $parametros['estado'] : null;
    $idEmpleado = isset($parametros['idEmpleado']) ? $parametros['idEmpleado'] : null;
    $idProductoPendiente = isset($parametros['idProductoPendiente']) ? $parametros['idProductoPendiente'] : null;

    $productoPedidosPendientes = ProductoPedido::InformarPendientesPorPerfil($perfil);
    $cantidadPendientes = count($productoPedidosPendientes);

    $productoAPreparar = ProductoPedido::ObtenerProductoPedidoPorId($idProductoPendiente);

    if($cantidadPendientes > 0)
    {

      if ($productoAPreparar->estado != "Pendiente") 
      {
        $payload = json_encode(array("mensaje" => "No hay pedidos pendientes con ese id"));
        $response->getBody()->write($payload);
        $response = $response->withStatus(400);
        return $response->withHeader('Content-Type', 'application/json');
      }

      if ($productoAPreparar->perfil != $perfil) 
      {
        $payload = json_encode(array("mensaje" => "El producto no correspode a su sector."));
        $response->getBody()->write($payload);
        $response = $response->withStatus(400);
        return $response->withHeader('Content-Type', 'application/json');
      }

      $pedidoTomado = $productoAPreparar;
      
      $pedidoTomado->idEmpleado = $idEmpleado;
      $pedidoTomado->estado = $estado;
      
      date_default_timezone_set('America/Argentina/Buenos_Aires');
      $tiempoDeTrabajo=random_int(10, 30);
      $pedidoTomado->horarioPautado=date('Y-m-d  H:i:s', strtotime("+{$tiempoDeTrabajo} minutes"));

      ProductoPedido::TomarPedidoPorPerfil($pedidoTomado);
      PedidoController::calcularTiempoDelPedido();
      LogController::CargarUno($request, "Empezar a preparar un pedido");  
      
      $payload = json_encode(array("mensaje" => "El " . $perfil ." ha tomado el pedido"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(200);
      return $response->withHeader('Content-Type', 'application/json'); 
    } 
    else 
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos pendientes"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');   
    }
  }

  public static function TerminarPedidoPorPerfil($request, $response, $args)
  {
    $perfil="";
    $uri=$_SERVER['REQUEST_URI'];
  
    switch($uri)
    {
      case "/comanda/app/ProductoPedido/TerminarPedidoBartender":
        $perfil="bartender";
        break;
      case "/comanda/app/ProductoPedido/TerminarPedidoCervecero":
        $perfil="cervecero";
        break;
      case "/comanda/app/ProductoPedido/TerminarPedidoCocinero":
        $perfil="cocinero";
        break;
    }
    $parametros = $request->getParsedBody();
    $estado = isset($parametros['estado']) ? $parametros['estado'] : null;
    $idProductoPedidoTerminado = isset($parametros['idProductoPedidoTerminado']) ? $parametros['idProductoPedidoTerminado'] : null;

    $productosPedidosEnPreparacion = ProductoPedido::InformarEnPreparacionPorPerfil($perfil);
    $cantidadPendientes = count($productosPedidosEnPreparacion);
    //$productoPedidoATerminar=$productosPedidosEnPreparacion[0];

    $productoATerminar = ProductoPedido::ObtenerProductoPedidoPorId($idProductoPedidoTerminado);

    if ($cantidadPendientes > 0) 
    {
      if ($productoATerminar->estado != "en preparacion") 
      {
        $payload = json_encode(array("mensaje" => "No hay pedidos en preparacion con ese id"));
        $response->getBody()->write($payload);
        $response = $response->withStatus(400);
        return $response->withHeader('Content-Type', 'application/json');
      }

      if ($productoATerminar->perfil != $perfil) 
      {
        $payload = json_encode(array("mensaje" => "El producto no correspode a su sector."));
        $response->getBody()->write($payload);
        $response = $response->withStatus(400);
        return $response->withHeader('Content-Type', 'application/json');
      }
     
      $productoATerminar->estado= $estado;
      $auxProductoATerminar = Producto::obtenerProductoPorId($productoATerminar->idProducto);
      ProductoPedido::modificarProductoPedido($productoATerminar);
      //$mensajeTrabajo = "Producto Pedido" => $productoATerminar->idProducto, "Estado" => "terminado";
      LogController::CargarUno($request, "Terminar de preparar un pedido");  
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No hay pedidos en preparacion"));
      $response->getBody()->write($payload);
      $response = $response->withStatus(400);
      return $response->withHeader('Content-Type', 'application/json');   
    } 

    if (ProductoPedidoController::VerificarEstadoProductosPorPedido($productoATerminar->codigoPedido))
    {
      $pedidoATerminar=Pedido::obtenerPedido($productoATerminar->codigoPedido);
      $pedidoATerminar->estado= "listo para servir";
      Pedido::modificarPedido($pedidoATerminar);
      $payload = json_encode(array("Producto del Pedido" => $productoATerminar->id, "Estado" => "terminado", "Pedido" => " El pedido se encuentra listo para servir"));
    } 
    else 
    {
      $payload = json_encode(array("Producto del Pedido" => $productoATerminar->id, "Estado" => "terminado", "Pedido" => " Aún quedan productos del pedido por terminar"));
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }


  public static function VerificarEstadoProductosPorPedido($codigoPedido)
  {
    $seccionesPedidos = ProductoPedido::obtenerSeccionPorCodigoPedido($codigoPedido);
    foreach($seccionesPedidos as $productoPedido)
    {
      $pedidoListoParaServir = true;
      if(strcmp($productoPedido->estado, "listo para servir")!=0)//////
      {
        $pedidoListoParaServir = false;
        break;
      }
    }

    return $pedidoListoParaServir;
  }

  public function EmitirInformeListosParaServirPorPerfil($request, $response, $args)
  {
    $uri=$_SERVER['REQUEST_URI'];
  
    switch($uri)
    {
      case "/comanda/app/ProductoPedido/InformeListosParaServirBartender":
        $perfil="bartender";
        break;
      case "/comanda/app/ProductoPedido/InformeListosParaServirCervecero":
        $perfil="cervecero";
        break;
      case "/comanda/app/ProductoPedido/InformeListosParaServirCocinero":
        $perfil="cocinero";
        break;
    }
    $pedidosListosParaServir = ProductoPedido::InformarListosParaServirPorPerfil($perfil);

    $cantidadListos = count($pedidosListosParaServir);
    if($cantidadListos > 0)
    { 
      LogController::CargarUno($request, "Emitir un listado de pedidos listos para servir");          
      $payload = json_encode(array($cantidadListos . " pedidos listos para servir. Detalle: "=> $pedidosListosParaServir));
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

  public function EmitirInformeProdOrdenadoPorCantVenta($request, $response, $args)
  {
    $pedidosConProductos = ProductoPedido::InformarProdOrdenadoPorCantVenta();
    $cantidadDePedidos = count($pedidosConProductos);
    $listaProdVendidos = array();

    if($cantidadDePedidos>0)
    {    
      foreach($pedidosConProductos as $pedidoConProd)
      { 
        $listaProdVendidos[] = array(
          "id Producto" => $pedidoConProd->idProducto,
          "Nombre del producto" => $pedidoConProd->nombre,
          "Cantidad Vendida" => $pedidoConProd->cantidad_vendida
        );
      }

      LogController::CargarUno($request, "Emitir listado de productos por cantidad vendida");  
      $payload = json_encode($listaProdVendidos);
      $response = $response->withStatus(200); 
    }
    else
    {
      $payload = json_encode(array("mensaje" => "No hubieron ventas."));
      $response = $response->withStatus(400);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }
}

?>