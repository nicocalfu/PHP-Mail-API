<?php
#Se definen 5 minutos maximo de procesamiento del script
set_time_limit(300);

// Configuración inicial, manejo de errores
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

#importacion de configuracion y funciones compartidas
include_once(__DIR__ . '/config/db.class.php');

#Configuracion de mailer
$correo_emisor  = Configuracion_Correo::obtenerConfiguracion()["CorreoEmisor"];
$nombre_emisor  = Configuracion_Correo::obtenerConfiguracion()["NombreEmisor"];
$contrasena     = Configuracion_Correo::obtenerConfiguracion()["Password"];

$mail = new PHPMailer();
$mail->IsSMTP();

$mail->Host = Configuracion_Correo::obtenerConfiguracion()["Host"];
$mail->SMTPAuth = Configuracion_Correo::obtenerConfiguracion()["SMTPAuth"];
$mail->Username = $correo_emisor;                                      
$mail->Password = $contrasena;
$mail->SMTPSecure = Configuracion_Correo::obtenerConfiguracion()["SMTPSecure"];
$mail->Port = Configuracion_Correo::obtenerConfiguracion()["Port"];
$mail->CharSet = 'UTF-8';

// Remitente
$mail->setFrom($correo_emisor, $nombre_emisor);
// Destinatario
$mail->AddAddress(Configuracion_Correo::obtenerConfiguracion()["CorreoDestinatario"], "");   // Correo del destinatario

// Definir correo tipo HTML
$mail->isHTML(true);

#conectar a la bd
$config = ConfiguracionBD::obtenerConfiguracion(); // Verifica que este método funcione correctamente
$conn = conectarBD($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

//Función que recibe como parametros los datos para la conexión a la BD y devuelve la conexión
function conectarBD($host, $usuario, $contrasena, $basededatos) {
    $conn = new mysqli($host, $usuario, $contrasena, $basededatos);

    // Verificar si hubo un error en la conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
        return null;
    }

    return $conn;
}

// Funcion que define la estructura del correo enviado
function correoFormat($referencia, $numeroContainer, $linkDocumento){
    $result = '
    <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="margin: 20px;">
                <p style="font-size: 16px;">Estimado,</p>
                
                <p style="font-size: 14px;">
                    Se informa que se registró un nuevo análisis de arribo DecoFrut, correspondiente a Wonderful Citrus. 
                    Los detalles del análisis son los siguientes:
                </p>
                
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; background-color: #f9f9f9;">Número de Referencia</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($referencia) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; background-color: #f9f9f9;">Número de Container</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($numeroContainer) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; background-color: #f9f9f9;">URL del Documento</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">
                            <a href="' . htmlspecialchars($linkDocumento) . '" style="color: #007BFF; text-decoration: none;">Ver Documento</a>
                        </td>
                    </tr>
                </table>
                
                <p style="font-size: 16px; font-weight: bold;">Saludos cordiales.</p>

                <hr style="border: 0; height: 1px; background: #ddd; margin: 20px 0;">

                <p style="font-size: 12px; color: #666;">
                    Este correo fue generado automáticamente. Por favor, no responda a este mensaje.
                </p>
            </div>
        </body>
    </html>
    ';
    return $result;
}

//Consulta que devuelve un arreglo con las referencias que NO estan en la tabla de sincronización
function consultaBDReferenciaSync($conn) {
    $sql = 'SELECT ope.referencia_op AS referencia,
            ot1.numeroContainer
            FROM tbl_operacion ope
            INNER JOIN tbl_embarqueope eope ON ope.idtbl_operacion = eope.codinstructivo_eoperacion
            INNER JOIN tbl_instructivoope iope ON iope.codinstructivo_ioperacion = ope.idtbl_operacion
            LEFT JOIN (
                SELECT
                ot.ContainerNumber_opetracking AS numeroContainer,
                ot.referencia_opetracking
                FROM tbl_operacion_tracking ot
            INNER JOIN (
            SELECT MAX(id_opetracking) AS max_id, referencia_opetracking
                FROM tbl_operacion_tracking
                GROUP BY referencia_opetracking
            ) AS latest ON latest.referencia_opetracking = ot.referencia_opetracking AND latest.max_id = ot.id_opetracking
            ) AS ot1 ON ot1.referencia_opetracking = ope.referencia_op
            LEFT JOIN tbl_sync_decofrut sync ON sync.numeroContainer = ot1.numeroContainer
            WHERE iope.codclie_ioperacion = 35
            AND sync.numeroContainer IS NULL
            AND ot1.numeroContainer IS NOT NULL
            AND ot1.numeroContainer <> "";
            ';
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[$row['numeroContainer']] = $row['referencia'];
        }

        return $data;
    } else {
        return [];
    }
}

function traerURLDocumentoLave($baseURL, $token, $arregloContainer, $tipoReporte) {
    $containersDisponibles = [];
    
    foreach ($arregloContainer as $numeroContainer => $referencia) {
        $url = construirURL($baseURL, $tipoReporte, $numeroContainer);

        list($respuestaApi, $httpCode) = realizarSolicitudAPI($url, $token);

        if ($httpCode !== 200) {
            continue;  // Continuar con el siguiente contenedor
        }

        $arrayAPI = json_decode($respuestaApi, true);
        if ($arrayAPI === null || !isset($arrayAPI['samples'][0]['sample']['ReportURL'])) {
            continue;  // Continuar con el siguiente contenedor
        }

        $linkDocumento = $arrayAPI['samples'][0]['sample']['ReportURL'];

        $containersDisponibles[$numeroContainer] = [
            'linkDocumento' => $linkDocumento,
            'referencia' => $referencia
        ];
    }

    return $containersDisponibles;
}

/* Funcion que devuelve un arreglo con numeroContainer y su respectiva referencia,
es util para el reporte de tipo TTRA */
function traerNumeroReferencia($conn, $arrayContainers) {
    $arrayContainerReferencia = [];

    // Consulta SQL para obtener la referencia de un contenedor
    $check_sql = "SELECT referencia_opetracking AS referencia 
                  FROM tbl_operacion_tracking 
                  WHERE ContainerNumber_opetracking = ?";
    $stmt = $conn->prepare($check_sql);

    // Iterar sobre el arreglo de contenedores
    foreach ($arrayContainers as $numeroContainer => $linkDocumento) {
        $stmt->bind_param("s", $numeroContainer);
        $stmt->execute();
        $result = $stmt->get_result();

        // Verificar si se obtuvieron resultados
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $referencia = $row["referencia"] ?? "";

            if (!empty($referencia)) {
                $arrayContainerReferencia[$numeroContainer] = [
                    'linkDocumento' => $linkDocumento,
                    'referencia' => $referencia
                ];
            }
        }
    }

    return $arrayContainerReferencia;
}

//Funcion que procesa el arreglo TTRA, devuelve numeroContainer y su linkDocumento
function procesarArregloTTRA($data){
    // Arreglo para almacenar los contenedores procesados con su respectivo link
    $containersProcesados = [];

    foreach ($data['samples'] as $sample) {
        if (isset($sample['sample']['anexos']) && is_array($sample['sample']['anexos'])) {
            foreach ($sample['sample']['anexos'] as $anexo) {
                // Verificar si el anexo es de tipo 'Container'
                if (isset($anexo['CodigoDetalleAnexo']) && $anexo['CodigoDetalleAnexo'] === 'Container') {
                    $linkDocumento = $sample['sample']['ReportURL']; // Link del documento

                    if (isset($anexo['ValorAnexo'])) {
                        $contenedor = $anexo['ValorAnexo'];
                        // Si hay varios contenedores, los separo usando explode()
                        $contenedores = strpos($contenedor, ',') !== false ? explode(",", $contenedor) : [$contenedor];

                        foreach ($contenedores as $numeroContainer) {
                            // Evitar procesar el mismo contenedor varias veces
                            if (isset($containersProcesados[$numeroContainer])) {
                                continue; // Si ya fue procesado, salto al siguiente
                            }
                            // Almacenar el contenedor con su respectivo link
                            $containersProcesados[$numeroContainer] = $linkDocumento;
                        }
                    }
                }
            }
        }
    }

    // Retornar el arreglo de contenedores procesados si es necesario
    return $containersProcesados;
}

//Función que realiza la consulta sql a la tabla de sincronización por número de container
function verificarNumeroContainer($conn, $numeroContainer, $tipoReporte){
    $check_sql = "SELECT urlDocumento FROM tbl_sync_decofrut WHERE numeroContainer = ? AND tipoReporte = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $numeroContainer, $tipoReporte);
    $stmt->execute();

    return $stmt->get_result();
}

// Devuelve un arreglo con los containers que no cuentan con un tipo de reporte TTRA en la tabla de sync
function contenedoresTblSync($conn, $arregloContainer, $tipoReporte) {
    $containersDisponibles = [];

    foreach ($arregloContainer as $numeroContainer => $linkDocumento) {
        $result_check = verificarNumeroContainer($conn, $numeroContainer, $tipoReporte);
        if ($result_check->num_rows <= 0){
            $containersDisponibles[$numeroContainer] = $linkDocumento;
        } 
    }
    return $containersDisponibles;
}

//Funcion que devuelve URL en base al tipo de analisis ingresado
function construirURL($baseURL, $tipoReporte, $numeroContainer = null) {
    $tipoReporte = strtoupper($tipoReporte);

    switch ($tipoReporte) {
        case "LAVE":
            if (!empty($numeroContainer)) {
                return $baseURL . "&IdTipoInspeccion=" . $tipoReporte . "&Container=" . strtoupper($numeroContainer);
            }
            throw new Exception("Se requiere un número de container para el tipo de reporte LAVE. <br>");
        case "TTRA":
            return $baseURL . "&IdTipoInspeccion=" . $tipoReporte . "&size=5000";
        default:
            throw new Exception("Tipo de reporte ingresado es incorrecto. <br>");
    }
}

//Funcion que realizada la llamada a la API
function realizarSolicitudAPI($urlAPI, $token) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $urlAPI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = array("exporter-token: $token");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);  // Se define 120 segundos para esperar la respuesta
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);  // Se define 30 segundos para esperar la conexión

    $respuesta_api = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($respuesta_api === false) {
        $errorMessage = curl_error($ch);
        curl_close($ch);
        return ["error" => $errorMessage, "httpCode" => 0];
    }

    curl_close($ch); 

    return [$respuesta_api, $httpCode];
}

//Función que ingresa los datos a la tabla de sincronización
function insertarTblSync($conn, $arrayContainerReferencia, $tipoReporte) {
    $table = 'tbl_sync_decofrut';
    $fechaActual = date('Y-m-d H:i:s');
    $estado = "Guardado";

    // Definir la consulta de ingreso de datos
    $sql = "INSERT INTO $table (referencia, numeroContainer, urlDocumento, tipoReporte, ultimaActualizacion, estado) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        throw new Exception('Error en la preparación de la consulta: ' . mysqli_error($conn) . '<br>');
    }

    // Iniciar una transacción
    mysqli_begin_transaction($conn);
    
    try {
        // Recorrer el arreglo y insertar cada fila
        foreach ($arrayContainerReferencia as $numeroContainer => $data) {
            // Validar si los datos necesarios están presentes
            if (empty($data['referencia']) || empty($data['linkDocumento'])) {
                throw new Exception("Faltan datos para el contenedor: " . $numeroContainer . "<br>");
            }

            // Vincular los parámetros
            mysqli_stmt_bind_param(
                $stmt, 
                "ssssss", 
                $data['referencia'], 
                $numeroContainer, 
                $data['linkDocumento'], 
                $tipoReporte, 
                $fechaActual, 
                $estado
            );

            // Ejecutar la sentencia
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al insertar fila para el contenedor $numeroContainer: " . mysqli_error($conn) . "<br>");
            }

            echo "Fila insertada correctamente para el contenedor: " . $numeroContainer . " con la referencia: " . $data['referencia'] . " y el tipo de reporte: " . $tipoReporte . "<br>";
        }

        // Confirmar transacción
        mysqli_commit($conn);
    } catch (Exception $e) {
        // Si ocurre un error, revertir la transacción
        mysqli_roll_back($conn);
        echo "Error: " . $e->getMessage();
    }

    // Cerrar la sentencia
    mysqli_stmt_close($stmt);
}

function enviarCorreo($mail, $arregloContainer){
    foreach ($arregloContainer as $numeroContainer => $data) {
        $linkDocumento = $data['linkDocumento'];
        $referencia = $data['referencia'];

        $mail->Subject = 'Nuevo análisis de arribo Decrofrut. Número de referencia: ' . $referencia;
        $mail->Body = correoFormat($referencia, $numeroContainer, $linkDocumento);
        $mail->send();
    }    
}

function funcionPrincipalTTRA($baseURL, $token, $conn, $mail) {
    try {
        $tipoReporte = "TTRA";

        $url = construirURL($baseURL, $tipoReporte);

        list($respuestaApi, $httpCode) = realizarSolicitudAPI($url, $token);

        if ($httpCode !== 200) {
            throw new Exception("Error al procesar la solicitud a la API, para el tipo de reporte TTRA. Código HTTP: " . $httpCode . "<br>");
        }

        $arrayAPI = json_decode($respuestaApi, true);
        if ($arrayAPI === null) {
            throw new Exception("Error al decodificar la respuesta de la API, para el tipo de reporte TTRA. <br>");
        }

        $arrayContainers = procesarArregloTTRA($arrayAPI);

        $verificarContenedoresTblSync = contenedoresTblSync($conn, $arrayContainers, $tipoReporte);
        if (empty($verificarContenedoresTblSync)) {
            throw new Exception("No existen nuevos registros para ingresar a la tabla de sincronización, para el tipo de reporte TTRA.<br>");
        }

        $nuevoAnalisisReferencia = traerNumeroReferencia($conn, $verificarContenedoresTblSync);
        if (empty($nuevoAnalisisReferencia)) {
            throw new Exception("No existen referencias para ingresar a la tabla de sincronización, para el tipo de reporte TTRA.<br>");
        }

        // Insertar en la tabla de sincronización
        insertarTblSync($conn, $nuevoAnalisisReferencia, $tipoReporte);
        enviarCorreo($mail, $nuevoAnalisisReferencia);

    } catch (Exception $e) {
        // Manejo de errores: se captura la excepción y se muestra el mensaje
        echo "Error: " . $e->getMessage();
        return;
    }
}

function funcionPrincipalLave($conn, $baseURL, $token, $mail) {
    try {
        $tipoReporte = "Lave";
        
        $arrayReferenciaContainer = consultaBDReferenciaSync($conn);
        
        if (empty($arrayReferenciaContainer)) {
            throw new Exception("No existen referencias para ingresar a la tabla de sincronización, para el tipo de reporte Lave.<br>");
        }
        
        $containerListos = traerURLDocumentoLave($baseURL, $token, $arrayReferenciaContainer, $tipoReporte);

        if (empty($containerListos)) {
            throw new Exception("No se pudo obtener la URL de documento desde la API, para el tipo de reporte Lave. <br>");
        }

        insertarTblSync($conn, $containerListos, $tipoReporte);
        enviarCorreo($mail, $containerListos);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

$baseURL = Configuracion_Apis::obtenerBaseUrl();
$token = Configuracion_Apis::obtenerToken();

funcionPrincipalTTRA($baseURL, $token, $conn, $mail);
funcionPrincipalLave($conn, $baseURL, $token, $mail);

$conn->close();

?>
