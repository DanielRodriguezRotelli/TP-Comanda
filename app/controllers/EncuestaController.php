<?php
require_once './models/Encuesta.php';
require_once './models/AutentificadorJWT.php';
require_once './controllers/LogController.php';
require_once './models/ProductoPedido.php';
require_once './controllers/PedidoController.php';

class EncuestaController extends Encuesta 
{
  public function CargarUno($request, $response, $args)
  {
    $parametros = $request->getParsedBody();

    $codigoPedido = $parametros['codigoPedido'];
    $puntuacionMesa = $parametros['puntuacionMesa'];
    $puntuacionRestaurante = $parametros['puntuacionRestaurante'];
    $puntuacionMozo = $parametros['puntuacionMozo'];
    $puntuacionCocinero = $parametros['puntuacionCocinero'];
    $comentarios = $parametros['comentarios'];

    $pedido = PedidoController::obtenerPedidoPorCodigo($codigoPedido);

    if($pedido)
    {
      $productoPedido = ProductoPedido::obtenerSeccionPorCodigoPedido($codigoPedido);
      $idCocineroAux = 0;
      foreach($productoPedido as $seccionPedido)
      {
        if(strcmp($seccionPedido->perfil, "cocinero") == 0)
        {
          $idCocineroAux = $seccionPedido->idEmpleado;
        }
      }
      
      $encuesta = new Encuesta();
      $encuesta->codigoPedido = $codigoPedido;
      $encuesta->idMesa = $pedido->idMesa;
      $encuesta->puntuacionMesa = $puntuacionMesa;
      $encuesta->puntuacionRestaurante = $puntuacionRestaurante;
      $encuesta->idMozo = $pedido->idMozo;
      $encuesta->puntuacionMozo = $puntuacionMozo;
      $encuesta->idCocinero = $idCocineroAux;
      $encuesta->puntuacionCocinero = $puntuacionCocinero;
      $encuesta->comentarios = $comentarios;
      $encuesta->crearEncuesta();

      $payload = json_encode(array("mensaje" => "Encuesta exitosa."));
    }
    else
    {
      $payload = json_encode(array("Error" => "Pedido inválido. Verifique los datos e intente nuevamente."));
    }    

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function EmitirInformeMejoresComentarios($request, $response, $args)
  {
    $encuestas = Encuesta::InformarMejoresComentarios();
    $comentarios = array();

    if ($encuestas) 
    {
      foreach ($encuestas as $encuesta) 
      {
        $comentarios[]=array(
          "Codigo Pedido" => $encuesta->codigoPedido,
          "Comentario" => $encuesta->comentarios
        );
        # code...
      }
      $payload = json_encode($comentarios);
    }
    else 
    {
      $payload = json_encode(array("Mensaje" => "no hay mejores comentarios"));
    }
    LogController::CargarUno($request, "Pedido de informe de mejores comentarios");  
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }     

  public function EmitirInformePeoresComentarios($request, $response, $args)
  {
    $encuestas = Encuesta::InformarPeoresComentarios();
    LogController::CargarUno($request, "Pedido de informe de peores comentarios");  
    $payload = json_encode(array("Peores comentarios de nuestros clientes: " => $encuestas));
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
  }   
}

?>