<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



use Illuminate\Support\Str;
class Dictionary
{
    private $_client;
    private $_survey;

    public function __construct($request)
    {
        $this->setData($request);
        $this->setClient($this->_data['client']);
        $this->setSurvey($this->_data['survey']);
    }

    /*** Diccionario ***/

    private $dictionaryList = [
        "Adicionales" =>[ "cargas adicionales", "muchos adicionales", "adicional", ],
        
        "Adecuación" =>[ "adecuacion", "adecuación", ],
        
        "Ágil" => [ "ágil", "proceso ágil", "fluidez", "práctico", ],
        
        "Agradable"=> [ "amabilidad", "cordialidad", "amorosa", "amable", "buen trato", "excelente trato", "cordial", "gentil", "gentileza",],
        
        "Anularon hora" => [ "anularon hora", "anular hora", "cancelaron hora", "cancelar hora", ],
        
        "Aprueban" => [ "aprueban", "aprobaron", "aprobación", "aprobacion", ],
        
        "Asesoría"=> [ "asesoraron", "orientacion", "excelente orientacion", "buena orientacion", "asesoria", "asesoría", "orientaron", "orientarán",],
        
        "Atencion" =>[ "amabilidad", "atención", "gentil", "servicio", "experiencia", "dijo", "gracias", ],
        
        "Atención"=> [ "excelente atención", "excelente atencion", "buena atención", "buena atencion", "atención personalizada", "excelente", "buena",],//Preguntar si puedo juntar estas dos
        
        "Atencion Conductor" => [ "bueno", "genial", "excelente", "malo", "correcto", "gusta", "pésimo", "cero", "irresponsable", "lamentable", ],
                
        "Autoatención"=>[ "nadie atiende", "autoatencion", "autoatención", "no atienden", "atienden", ],

        "Beneficios"=>[ "beneficio", "beneficioso", "sin beneficios", "con beneficios", ],

        "Bono" => [ "bono", "compra del bono", "compra bono", "comprar bono", ],
        
        "Buen servicio" => [ "excelente servicio", "buena atención", "excelente atención", "soporte", "buen servicio", ],
        
        "Buena gestión"=> [ "gestionaron", "buena gestión", "excelente gestión", ],
        
        "Buena información"=> [ "información concuerda", "información clara", "información entendible", "informacion concuerda", "informacion clara", "informacion entendible", ],
        
        "Buena opción " => [ "buena elección", "opción correcta", ],

        "Buena página" => [ "buena página", "buena pagina", "buen sitio", "página clara", "pagina clara", ],

        "Buena Respuesta"=> [ "buena respuesta", "respuesta clara", "excelente respuesta", "respuesta oportuna", ],
        
        "Buenos planes" => [ "excelentes planes", "planes convenientes", "planes buenos", "buenos planes", "planes accesibles", ],

        "Burocracia"=>[ "no cumplen", "demoroso", "incumplen", "demoran", "demorarían", "irresponsanble", "burocracia", ],

        "Calidad" =>[ "bueno", "genial", "excelente", "malo", "correcto", "gusta", "pesimo", "cero", "gracias", "irresponsable", "lamentable", ],
        
        "Calidad del vehiculo" => [ "bueno", "genial", "excelente", "malo", "correcto", "gusta", "pésimo", "cero", "irresponsable", "lamentable", ],
        
        "Calidad Prestador" => [ "prestador", "pocos prestadores", "muchos prestadores", "prestadores reducidos", "pocas clínicas", ],

        "Cambio prestador" => [ "gestión", "modificar prestador", "modificacion de prestador", "modificar un prestador", "modificación de prestador", ],

        "Canal" =>[ "app", "web", "contact", "call", "teléfono", "mail", "counter", "aeropuerto", ],

        "Canasta" =>[ "canasta", "canasta cara", "pago canasta", "cambio canasta", "modificar canasta", "modificacion de canasta", "modificación de canasta", ],

        "Cantidad horaria" => [ "pocas horas", "horarios reducidos", "horas reducidas", ],

        "Claridad del plan" =>[ "poco claro", "muy claro", "claro", "super claro", ],

        "Claro" => [ "claro", "claridad", ],

        "Cobertura" => [ "buenos planes", "mucha cobertura", "mala cobertura", "poca cobertura", "muchos planes", ],
        
        "Coberturas" =>[ "sin conbertura", "fuera de cobertura", "buena cobertura", "falta de cobertura", "buenas coberturas", "conberturas", ],
        
        "Comodidad" => [ "poco cómodo", "incómodo", "muy cómodo", "cómodo", "poco comodo", "incomodo", "muy comodo", "comodo", "agradable", "desagradable", ],
     
        "Cómodo" => [ "cómodo", "comodo", "comodidad", ],
        
        "Compin" => [ "compin", ],
        
        "Comprensible" => [ "entendible", "comprensible", "entender", "comprender", ],

        "Compromiso" => [ "sin compromiso", "poco compromiso", "comprometido", "comprometida", "compromiso", ],
        
        "Comunicación"=> [ "comunicaron", "avisar", "avisaron", "comunican", "avisan", "avisarán", "avisaran", "comunicar", "comunicarán", "comunicaran",],
        
        "Concordancia"=>[ "buena concordancia", "ninguna concordancia", "concordancia", ],
        
        "Conductor" => [ "chofer", "gentileza", "atención", "genial", "gracias", "amabilidad", "conductor", "irresponsable", "pésimo", ],
        
        "Conexión" => [ "conexión", "conexion", ],
        
        "Confiabilidad"=> [ "muy creible", "super creible", ],
        
        "Confianza" => [ "transparencia", "claridad", "claro", "transparente", "confianza", ],

        "Conformidad"=>[ "conforme", "muy conforme", "totalmente conforme", "satisfecho", "muy satisfecho", "insatisfecho", "nada satisfecho", "insatisfacción", "insatisfaccion", "poco conforme", "inconformidad", "nada conforme", ],
        
        "Constancia"=> [ "constante", "constancia", ], 
        
        "Continuidad" => [ "continuidad", "continuo", ],
        
        "Convenios" =>[ "sin convenio", "fuera de convenio", "buen convenio", "falta de convenio", "buenos convenios", "convenio", ],
        
        "Coordinacion" => [ "coordinación", "pésimo", "atención", "cobro en aeropuerto", "pago en aeropuerto", "vueltas del viaje", "servicio", "cumple", "puntualidad", ],

        "Cruz verde" =>[ "cruz verde", "cruzverde", ],
       
        "Cuestionar" => [ "cuestionar", "cuestionan", "cuestionamiento", ],

        "Cumplimiento"=>[ "cumplir", "incumplen", "no cumplen", "si cumplen", "cumplen", ],
       
        "Demora licencia" => [ "demora licencia", "demoraron la licencia", ],
       
        "Despachan" =>[ "despachan", "despachar", "despachado", "despacharian", "despacharían", ],

        "Dictamen" => ["dictámen","dictamen","resultado", ],

        "Dificil" => [ "difícil", "dificil", "complicado", "enredado", ],
       
        "Disminución de licencias" => [ "disminución de licencia", "disminucion de licencia", "reduccion de licencia", "reducción de licencia", ],
        
        "Disponibilidad"=>[ "disponible", "poco disponible", "no disponible", "siempre disponible", ],
    
        "Efectividad" => [ "efectividad", "efectivo", ],
        
        "Eficiencia"=>[ "agilidad", "experiencia", "funciona", "agil", "ágil", "funcionar", "eficiente", ],

        "Eficiencia de servicio" => [ "eficiencia de servicio", "sin espera", "sin esper", "servicio eficiente", ],

        "Eficiente" => [ "eficiente", "eficiecia", "rapidez", "rápido", "rapido", ],

        "Engorroso" => [ "engorroso", "proceso engorroso", "complicado", ],

        "Ejecutivo"=> [ "ejecutivo", "ejecutiva", "asesor", "asesora", ],

        "Ejecutivo un 7"=> [ "ejecutiva un 7", "comprometida", "buena ejecutiva", "buen ejecutivo", "excelente ejecutiva", "excelente ejecutivo", ],
        
        "Error clínica" => [ "error", "mala clínica", "error en la clínica", "error clínica", "error en la clinica", "error clinica",],
        
        "Estafa" => [ "estafadores", "estafar", "estafan", "robar", "roban", "engañan", "engañar", "mentiroso", ],
        
        "Espera" =>[ "tarde", "pésimo", "tiempo de espera", "preferencia", "plazo", "convenido", "acordado", ],

        "Excesos" =>[ "excesos", "exceso", "sobrante", ],
        
        "Excedentes" =>[ "excedente", "excedentes", ],
        
        "Explicación"=> [ "preocupacion", "explicaron", "dedicacion", "preocuparon", "dedicaron", "explicación", "explicacion", "preocupar", "dedicar", "explicar", ],
        
        "Fácil" => [ "fácil", "fácil acceso", "sencillo", ],
        
        "Fácil afiliarse" => [ "sencillo afiliarse", "facil afiliarse", "fácil afiliarse", "afiliacion simple", "afiliación simple", "afiliacion sencilla", "afiliación sencilla", ],

        "Falta información" => [ "falta información", "falta informacion", "mala información", "mala informacion", "escasa información", "escasa informacion", "poca información", "poca informacion", ],

        "Farmacia" =>[ "farmacia", "farmacia en convenio", "convenio farmacia", ],

        "Fluidez"=>[ "expedito", "fluido", ],
        
        "GES" => [ "compra GES", "compra bono GES", "bono GES", "atención GES", "atencion GES", "canasta GES", ],
        
        "Información" => [ "información", "falta información", "falta informacion", "informacion", "mala información", "mala informacion", "buena información", "buena informacion", "pésima información", "pesima informacion", ],

        "Información clara" => [ "información clara", "informacion clara", ],

        "Insistencia"=> [ "insistencia", "molestos", "agobio", "insistente", "molestosos", "molestar", "agobiar", ],
        
        "Integramédica" => [ "integramedica", "integramédica", ],

        "Largo" => [ "largo", "extenso", "tedioso", ],

        "Lentitud"=> [ "lentos", "demorosos", "demorar", "lentisimo", "lentísimo", "demoraron", ],

        "Lento" => [ "lento", "tedioso", "demoroso", "demorar", "lentitud", ],

        "Licencias"=>[ "licencia médica", "licencia medica", "LM", "L.M.", "licencias", ],

        "Mala isapre" => [ "mala isapre", "mal necesario", "malditas isapres", ],
        
        "Medicamento" =>[ "medicamento", "entrega medicamento", "cobro medicamento", "ingresar medicamento", "solicitud medicamento", ],

        "Maternal" => [ "maternal", ],

        "Mejorar" => [ "mejorarían", "mejorar", "mejoras", "mejorarian", ],

        "Muy buena isapre" => [ "buena isapre", "excelente isapre", "empresa sólida", "empresa solida", ],

        "Nada especial" => [ "poco especial", "nada especial", ],
        
        "Online" => [ "servicio online", "canal digital", "canales digitales", ],

        "Ordenado" => [ "ordenado", "orden", "ordenar", "limpio", ],

        "Pago" => [ "pago", "pago licencia", "pago total", "pago oportuno", "pago satisfactorio", "pago a tiempo", ],

        "Pésima atención"=> [ "mala atencion", "mala atención", "muy mala", "pésima atención", "pesima atencion", "atención mala", "atencion mala", ],

        "Pésima información"=> [ "falta información", "información confusa", "mala información", "confuso", "información faltante", "información incompleta", "falta informacion", "informacion confusa", "mala informacion", "informacion faltante", "informacion incompleta", ],
        
        "Planes" =>[ "plan", "buenos planes", "buen plan", "excelente plan", "excelentes planes", "planes accesibles", ],
        
        "Planificacion" => [ "tiempo", "planificación", "planificar", "planificaron", "planificarán", ],
        
        "Plataforma" => [ "web", "aplicación", "aplicacion", "app", "sitio web", "pagina web", "página web", ],
        
        "Poca Claridad"=> [ "poco claro", "no se entiende", "nada claro", ],
       
        "Prestadores" =>[ "excelente prestación", "excelente prestacion", "muchos prestadores", "pocos prestadores", "malos prestadores", "buenos prestadores", "prestador", "mala prestacion", "mala prestación", ],
        
        "Problemas"=>[ "siempre problemas", "muchos problemas", "solo problemas", "sólo problemas", "solamente problemas", "sin problema", "ningun problema", "ningún problema", ],

        "Problemas link" => [ "problemas link", "link con problemas", "link", "link de acceso", ],

        "Profesional"=> [ "excelente", "muy", "malo", "poco", ],
        
        "Profesionales" => [ "profesionales", "médico", "medico", "doctor", ],
        
        "Puntualidad" =>[ "coordinación", "planificación", "tiempo", "puntualidad", "puntual", "puntuales", "planificar", "coordinar", "espera", "esperaron", ],

        "Rango horario" => [ "fuera de horario", "malos horarios", ],
        
        "Rapidez"=> [ "rapidez", "agilidad", "agil", "rapido", "rápido", "ágil", ],
        
        "Rápido" => [ "rápido", "rapido", "rapidez", "agilidad", "expedito", "agil", "ágil", ],
        
        "Receta" =>[ "receta", "recetar", ],
        
        "Rechazo"=>[ "rechazo", "rechazaron", "rechazan", "rechazarán", "rechazaran", "rechazar", "rechazado", "rechazada", ],
       
        "Reclamo" =>[ "reclamo", "reclamar", "reclame", "reclamaría", "alegar", "alegaría", ],
        
        "Reducir" => [ "reducir", "reducción", "reduccion", "reducen", "redujeron", "reducieran", "reducieron", "reducirán", "reduciran", ],

        "Reembolso"=>[ "reembolsar", "reembolsaron", "reembolsarian", "reembolsarían", "reembolsaran", "reembolsarán", "reembolso", "reembolsado", ],
        
        "Remedio" =>[ "remedio", "reducen remedios", "baja calidad remedio", "reducen remedio", ],
        
        "Remoto" => [ "vía remota", "via remota", "canal remoto", "remoto", ],
        
        "Resolución" => ["resolución","resolucion", ],
        
        "Respuesta" =>[ "respuesta", "responder", "información", "informaron", "respondieron", "responderían", "contaron", "contarían", "dijeron", "dijo", "contestar", "informar", ],
        
        "Resultados" => [ "resultado", ],
        
        "Salcobrand" =>[ "salcobrand", ],
        
        "Salud" => [ "servico de salud", "salud", ],

        "Seguridad" => [ "seguridad", "seguro", "precaución", ],

        "Seguro" => ["seguro","seguridad", ],

        "Servicio" =>[ "reserva", "ruta", "camino", "salida", "servicio", "traslado", "cumple", "viaje", "puntualidad", ],

        "Servicios"=>[ "excelente servicio", "buen servicio", "buena atención", "buena atencion", "mal servicio", "mala atención", "mala atencion", "pésimo servicio", "pesimo servicio", "pésima atención", "pesima atencion", ],
        
        "Servicio prestador" => [ "pésima clínica", "malos centros", "malos prestadores", "mal prestador", "mal centro", ],

        "Simple" => [ "simple", "simpleza", "sistema simple", "sistema facil", "sistema fácil", ],

        "Sistema malo" => [ "pésimo sistema", "mal sistema", "sistema malo", "pesimo sistema", "sistema horrible", ],

        "Solicitudes"=> [ "solicitudes", "solicitud", ],
        
        "Tarifa" => [ "valor", "pago", "caro", "barato", "pagaron", "pagaría", "cobro", "cobraron", "cobrarán", ],
        
        "Tecnología" => [ "tecnología", "tecnologia", "tecnologia antigua", "tecnologia moderna", "mala tecnologia", "tecnología antigua", "tecnología moderna", "mala tecnología", "equipo", "equipo antiguo", "equipo moderno", "malos equipos", "equipos antiguos", "equipos modernos", ],
        
        "Tiempo" =>[ "tiempo", "demora", "rapidez", "veloz", "límites", "lento", ],

        "Tiempo atención" => [ "tiempo limitado", "tiempo acotado", "poco tiempo", "buen tiempo", "escaso tiempo", "tiempo atención", "tiempo atencion", ],

        "Tiempo de Espera" => [ "tarde", "cumple", "tiempo de espera", "preferencia", "plazo", "cumplir", "cumplieron", "cumplirán", ],
        
        "Trámite" => [ "trámite", "tramite", ],
        
        "Tramitación"=>[ "tramitar", "tramitacion", "tramitación", "tramitaron", ],
        
        "Trámite en línea"=>[ "tramite online", "solicitud online", "online", "trámite online", "gestión online", "gestion online", ],
        
        "Transparencia"=> [ "transparente", "claro", "claridad", "transparencia", ],
        
        "Tratamiento" =>[ "tratamiento", ],
       
        "Valor Plan" =>[ "plan caro", "plan barato", "plan accesible", "planes caros", "planes baratos", "planes accesibles", ],
        
        "Valor planes" => [ "valor planes", "precio planes", "precio de los planes", "valor plan", "precio plan", ],

        "Valor prestación" =>[ "atenciones caras", "bono caro", "valor", "valores", "precio", "precios", ],

        "Vehiculo" => [ "coordinacion", "planificación", "tiempo", "puntualidad", "puntual", "planificar", "coordinar", ],

        "Viaje" =>[ "precaución", "furioso", "tranquilo", "prudente", "cauto", "respetuoso", ],
        //Diccionario Jetsmart Relacional via
        "COMPRA"=>["compra","comprar","compré","facilidad de compra","facilidad de pago","pagué","pague","añadir","añadi","pagaron","pagaba","dificultad compra","dificultad pago","dificil pago","dificil pagar","dificil comprar","difícil pago","difícil pagar",],
        "VUELO"=>["disponibilidad de vuelo","facilidad elección","vuelo","destinos","itinerario","fácil elegir","facil elegir","disponible","destino","ruta","llegada",],
        "HORARIO"=>["disponibilidad de fecha","facilidad elección","fecha","horario vuelo","horario de vuelos comprados","horarios","hora de viaje","horario disponible",],
        "SITIO WEB"=>["facilidad de ingreso a la página","sitio web","pagina web","disponibilidad de la web","facilidad de uso","plataforma web","sitio web facil","sitio web intuitivo","sitio web amigable","página web","sitio web fácil","sucursal virual","web","no carga","se cae","página lenta","sitio lento","web lenta","pagina lenta",],
        "PRECIOS BAJOS"=>["precios bajos","baratos","economicos","tarifa baja","low cost","buenas ofertas","buenas promociones","buenos descuentos","económico","buen precio","precio bajo","",],
        "PRECIOS ALTOS"=>["precios altos","precios caros","caros","tarifa alta","malas ofertas","malas promociones","malos descuentos","caro","mal precio","precio alto","precio elevado","costoso","costo elevado","costo alto","",],
        "ESTAFA"=>["estafa","estafaron","engaño","engañaron","robo","robaron",],
        "RAPIDEZ"=>["rapidez de compra","agilidad de compra","proceso ágil","proceso agil","proceso rápido","proceso rapido","fácil comprar","facilidad para comprar","facil comprar",],
        "COBRO" =>["cobro excesivo","cobro indebido","cobro extra","politica de cobro","cobro elevado","cobro exagerado","caro el cobro","política de cobro","no entendí cobro","no se entiende cobro","no es claro cobro",],
        "PAGO"=>["pago","pagos","pagar","pagaron","pague","pago por equipaje","pago excesivo","pago extra","pago alto","pago justo","pagué",],
        "RECHAZO"=>["pago fallido","pago rechazado","error de pago","problemas al pagar","no recibe tarjeta","no recibe pago","problema para pagar","no aplica pago",],
        "MEDIO DE PAGO" =>["medio de pago","tarjeta de credito","tarjeta de debito","credito","debito","casa comercial","cuotas ","banco","tarjeta de crédito","tarjeta de débito","débito","crédito","transferencia bancaria","transferir","efectivo","pago en efectivo","diners","visa","mastercard","american express",],
        "NO RECEPCION EMAIL"=>["spam","no llegó ","no llega","no recepción email","demora en recepcion email","demora en recepción email","no llego","no recibi","no recibí","nunca llegó","nunca llego","no deseado",],
        "INFORMACION EMAIL"=>["información email completa","email detallado","email completo","email claro","correo completo","correo claro","información  correo","informacion correo",],
        "INFORMACION EMAIL MALA"=>["falta información email","poca información email","falta detalle en email","email incompleto","muchos email","mensaje incompleto",],
        "MAIL" =>["mail","email","e mail","correo",],
        "FACILIDAD"=>["checkin facil","check in facil","check in fácil","checkin fácil","check in simple","checkin simple",],
        "DIFICULTAD"=>["checkin dificil","checkin complicado","dificultad para ckeck in","dificil hacer check in","difícil hacer check in","dificil hacer checkin","difícil hacer checkin","imposible hacer ckeck in",],
        "CLARIDAD"=>["checkin claro",],
        "RAPIDEZ"=>["checkin rapido","checkin expedito","checkin agil","check in ágil","checkin rápido","check in rapido","check in rápido",],
        "LENTITUD" =>["checkin lento","checkin demoroso","check in lento","check in demoroso","check in demorado","check in lento",],
        "FACIL"=>["registro facil","claro","simple","sencillo","rapido","registro fácil","rápido","claridad en registro",],
        "DIFICIL"=>["registro dificil","complicado","tediosos","engorroso","demoroso","registro difícil","registro complicado",],
        "PRECIO ALTO"=>["precio alto","caro","costoso","costo extra","más alto","más caro","precio mas alto","mas caro ","mas costoso","más costoso","precio más alto","costo equipaje",],
        "PRECIO BAJO"=>["economico","precio equipaje barato","económico ","precio bajo","buen precio","precio justo",],
        "AMABILIDAD"=>["trato amable","gentil","empatía","alegre","cordial","empático","empatico","buena atención","buena atencion","personal amable","me ayudan ","me ayudaron",],
        "NO AMABILIDAD"=>["poco amable","poco gentil","poco empático","pesado","desagradable","poco cordial","desatento","desatenta","mala atención","no me atendieron","no atienden","no ayudan","no ayudaron",] ,
        "ABORDAJE FACIL"=>["facil","claro","expedito","ordenado","organizado","fácil","confuso",],
        "ABORDAJE DIFICIL"=>["dificil","poco claro","mala informacion","desordenado","desorganizado","difícil","problema para abordar","inconvenientes para abordar",],
        "HORARIO"=>["horario abordaje","cumplir horario","incumplir horarios","tarde","temprano",],
        "RAPIDO"=>["rapido","agil","atencion rapida","rápido","ágil",],
        "LENTO"=>["lento","demorado","atencion lenta","retraso","retrasado","demora en abordar",],
        "EQUIPAJE"=>["cobro extra","cobro equipaje","peso equipaje",],
        "CANCELACIÓN"=>["cancelación vuelo","modificación horario","modificación vuelo",],
        "EMBARQUE PRIORITARIO"=>["embarque prioritario",] ,
        "AMABILIDAD"=>["amabilidad personal","gentileza","atentos","cordial","buena disponibilidad","buena disposición","empatía","me ayudaron","me ayudaban","buena atención","buena atencion","excelente atención","excelente atencion",],
        "NO AMABILIDAD"=>["poca gentileza","poca amabilidad","poco atentos","poca cordialidad","mala disponibilidad","mala disposición","poca disposibión","no ayudan","no ayudaron","no ayudaban","inconforme","disconforme",],
        "ASIENTOS"=>["asignacion asientos","poca claridad asientos","poca informacion asientos","mala asignacion","problemas de asignacion","sillas","cómodo","comodidad","comodo","incómodo",],
        "LIMPIEZA"=>["limpio","sucio","orden","desorden","cochino","manchado","ordenado","desordenado","limpieza",],
        "ALIMENTOS" =>["caros","baratos","disponibilidad","mala calidad","buena calidad","malos ","sabroso","precio","pago alimento","pagar alimento","pague alimento","alimentacion","alimentación","poca calidad",],
        "SALIDA"=>["salida","claridad","informacion","orden","trayecto","bajar del avión","bajar del avion","salir del avion","salir del avión",],
        "EQUIPAJE"=>["bueno","mal estado","perdido","dañado","extravio","claridad","extravío","incompleto","no llegó equipaje","no llego equipaje","extraviado","pérdida equipaje","equipaje dañado","daño equipaje",],
        "CINTA" =>["cinta equipaje","lejos","mal estado","no funciona","trayecto","cerca","cinta dañada",],
        "AMABILIDAD"=>["amabilidad","gentileza","empatía","atencion","atención","respeto","respetuoso","buena atención","excelente atención","amable","cordial","atento","interesado","interesada","me ayudan","me ayudaron","me ayudaban",],
        "CLARIDAD"=>["claridad","respuesta","solucion","solución","solucionaron","claros",],
        "CANALES"=>["canales","pocos canales","disponibilidad","no contestan","comunicación","comunicacion","teléfono","telefono","telefónico","call center","facil comunicarse","fácil comunicarse","contactarse",],
        "INFORMACION"=>["mala información","poca información","pocos detalles","comunicación","mala informacion","poca informacion","explicación","explicaron",],
        "REQUERIMIENTO"=>["requerimiento","reclamo","solicitud","consulta","pedido","petición","peticion",],
        "TIEMPO"=>["demoroso","lento","poco tiempo","tiempo de atencion","demorado","tiempo de atención","tiempo de respuesta","espero","esperando","esperar","espera",],
    ];

    /*** Banmedica Groups***/

    private $groupBanBase = [
        "ATENCIÓN CANALES" => [ "Atención", "Buena Respuesta", "Agradable", "Rapidez", "Asesoría", "Insistencia", "Lentitud", "Explicación", "Profesional", "Constancia", "Buena gestión", "Ejecutivo un 7", "Pésima atención", ],
        "INFORMACIÓN ISAPRE" =>[ "Transparencia", "Comunicación", "Confiabilidad", "Buena información", "Pésima información", "Ejecutivo", "Solicitudes", "Poca Claridad", ],
        "SERVICIO DE LA ISAPRE" =>[ "Conformidad", "Reembolso", "Licencias", "Rechazo", "Burocracia", "Problemas", "Eficiencia", "Trámite en línea", "Servicios", "Disponibilidad", "Fluidez", "Concordancia", "Beneficios", "Cumplimiento", "Autoatención", "Tramitación", ],
        "COBETURA/PLANES/PRECIOS" =>[ "Convenios", "Coberturas", "Valor prestación", "Prestadores", "Valor Plan", "Planes", "Excedentes", "Excesos", "Claridad del plan", "Adicionales", "Adecuación", ]
    ];

    /*** Banmedica Groups***/

    private $groupTraBase = [
        "TIEMPO PARA ENCONTRAR VEHICULO" => [ "Espera", "Puntualidad", "Calidad" ],
        "TIEMPO LLEGADA VEHICULO" => [ "Espera", "Puntualidad", "Calidad" ],
        "PUNTUALIDAD" => [ "Tiempo de Espera", "Puntualidad", "Calidad" ],
        "TIEMPO ESPERA VEHICULO AEROPUERTO" => [ "Espera", "Vehiculo", "Calidad" ],
        "COORDINACION ANDEN EMBARQUE" => [ "Coordinacion", "Planificacion", "Atencion Conductor" ],
        "SEGURIDAD" => [ "Seguridad", "Calidad del vehiculo" ],
        "RUTA Y TIEMPO" => [ "Servicio", "Tiempo", "Viaje" ],
        "ATENCIÓN CONDUCTOR" => [ "Conductor", "Tarifa" ],
        "ATENCIÓN EJECUTIVO" => [ "Atencion", "Reclamo", "Canal", "Respuesta"],
    ];

    /*** JetSmart Groups***/

    private $groupJetBase = [
        "COMPRAS" => [ "COMPRA", "VUELO", "HORARIO", "SITIO WEB", "PRECIOS BAJOS", "PRECIOS ALTOS", "ESTAFA", "RAPIDEZ", "COBRO" ],
        "PAGOS" => [ "PAGO", "RECHAZO", "MEDIO DE PAGO" ],
        "INFORMACION EMAIL" => [ "NO RECEPCION EMAIL", "INFORMACION EMAIL", "INFORMACION EMAIL MALA", "MAIL" ],
        "CHECKIN" => [ "FACILIDAD", "DIFICULTAD", "CLARIDAD", "RAPIDEZ", "LENTITUD" ],
        "REGISTRO EQUIPAJE" => [ "FACIL", "DIFICIL", "PRECIO ALTO", "PRECIO BAJO", "AMABILIDAD", "NO AMABILIDAD" ],
        "ABORDAJE VUELO" => [ "ABORDAJE FACIL", "ABORDAJE DIFICIL", "HORARIO", "RAPIDO", "LENTO", "EQUIPAJE", "CANCELACIÓN", "EMBARQUE PRIORITARIO" ],
        "VUELO" => [ "AMABILIDAD", "NO AMABILIDAD", "ASIENTOS", "LIMPIEZA", "ALIMENTOS" ],
        "LLEGADA VUELO" => [ "SALIDA", "EQUIPAJE", "CINTA" ],
        "SERVICIO AL CLIENTE" => [ "AMABILIDAD", "CLARIDAD", "CANALES", "INFORMACION", "REQUERIMIENTO", "TIEMPO"],
    ];

    public function getTexts() {
        $group = [];
        $resp = [];

        if($this->_client == 'ban'){
            $group[] = $this->groupBanBase;

            //Decirle a Fermin para corregir estos if
            if($this->_survey == 'rel' || $this->_survey == 'amb' || $this->_survey == 'hos' || $this->_survey == 'ges' || $this->_survey == 'tel'){
                $group[0]["PRESTADORES"] =  [ "Calidad Prestador", "Servicio prestador", "Cobertura", "GES", "Buena opción ", "Error clínica", "Integramédica", "Cantidad horaria", "Rango horario", "Tiempo atención", "Tecnología", "Profesionales", "Plataforma", "Cambio prestador", ]; 
            }

            if($this->_survey == 'rel'){
                $group[0]["PROPUESTA DE VALOR"] = [ "Confianza", "Buenos planes", "Respuesta", "Estafa", "Mejorar", "Muy buena isapre", "Comprensible", "Remoto", "Sistema malo", "Mala isapre", "Valor planes", "Fácil afiliarse", "Buen servicio", "Online", "Compromiso", "Salud", "Comodidad", "Nada especial", ];
            }

            if($this->_survey == 'mod' || $this->_survey == 'lic' || $this->_survey == 'web' || $this->_survey == 'ven'){
                $group[0]["PROCESOS"] = [ "Simple", "Anularon hora", "Trámite", "Rápido", "Buena página", "Lento", "Ordenado", "Eficiente", "Fácil", "Claro", "Información", "Largo", "Ágil", "Dificil", "Bono", "Engorroso", "Seguro", "Información clara", "Efectividad", "Conexión", "Continuidad", "Eficiencia de servicio", "Cómodo", "Reembolso", "Resultados", "Problemas link", ];
            }

            if($this->_survey == 'ges'){
                $group[0]["CANASTA/MEDICAMENTOS"] =[ "Medicamento", "Canasta", "Farmacia", "Remedio", "Receta", "Despachan", "Salcobrand", "Tratamiento", "Cruz verde", ];
            }

            if($this->_survey == 'lic'){
                $group[0]["DICTAMEN"] = [ "Cuestionar", "Rechazo", "Aprueban", "Demora licencia", "Disminución de licencias", "Dictamen", "Reducir", "Compin", "Pago", "Resolución", "Falta información", "Maternal", ];
            }
        }

        if($this->_client == 'tra'){
            $group[] = $this->groupTraBase;
        }

        if($this->_client == 'jet'){
            $group[] = $this->groupJetBase;
        }
        
        foreach ($group[0] as $key1 => $value) {
            foreach ($value as $key2 => $val) {
                $resp[$key1][$val] = $this->dictionaryList[$val];
            }
        }
        return $resp;
    }

    public function setData($request){
        $this->_data = [
            'client' => substr($request->get('survey'), 0, 3),
            'survey' => substr($request->get('survey'), 3, 3),
        ];
    }

    public function setClient($client){
        $this->_client = $client;
    }

    public function setSurvey($survey){
        $this->_survey = $survey;
    }

    public function getData(){
        return $this->_data;
    }

}