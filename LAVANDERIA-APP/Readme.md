# LavanderApp 🧺

Hola, soy **Felipe Muñoz López**, estudiante de 2º DAM en CampusFP, y este es mi Trabajo de Fin de Grado.

---

## ¿De qué trata el proyecto?

LavanderApp es una aplicación web diseñada para gestionar de forma digital todos los procesos de una lavandería. La idea nace de la necesidad de digitalizar un negocio que habitualmente se gestiona con papel y de forma manual, lo que genera errores, pérdidas de información y una gestión poco eficiente.

Con esta aplicación, el personal de la lavandería puede gestionar clientes, pedidos, sacos de ropa, facturación y productos desde cualquier navegador, sin necesidad de instalar nada en el ordenador del cliente.

---

## ¿Cómo funciona?

El sistema está pensado para dos tipos de usuarios con acceso diferente:

### Administrador (CEO de la empresa)
Tiene acceso completo a todas las funcionalidades:
- Ver el panel general con estadísticas en tiempo real
- Gestionar clientes: crear, editar y eliminar
- Gestionar pedidos y cambiar su estado
- Ver los sacos recibidos cada día por fecha
- Generar facturas automáticamente al final de cada mes por cliente
- Controlar el stock de productos con alertas de mínimo
- Gestionar los usuarios del sistema y asignar roles

### Empleado
Tiene acceso a las operaciones del día a día:
- Ver un resumen de los pedidos activos y pendientes
- Registrar nuevos pedidos asociados a clientes
- Añadir sacos de ropa indicando el tipo y el peso
- Consultar los sacos recibidos cada día
- Gestionar clientes
- Ver el estado del stock y recibir alertas si algún producto está bajo mínimo

### Flujo de trabajo típico
1. Un cliente llega a la lavandería con su ropa
2. El empleado lo registra en el sistema si no existe
3. Crea un pedido asociado a ese cliente
4. Añade los sacos de ropa con su tipo y peso (blanca, color, delicada, toallas, alfombrines, etc.)
5. El sistema calcula automáticamente el coste según el peso y el tipo de ropa
6. El pedido avanza por estados: Pendiente → En proceso → Finalizado → Entregado
7. Al final del mes, el administrador genera las facturas automáticamente con IVA incluido

---

## Tecnologías utilizadas

| Capa | Tecnología |
|------|-----------|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Base de datos | MySQL |
| Servidor local | XAMPP (Apache + MySQL) |
| Editor | Visual Studio Code |

---

## Estructura del proyecto

```
lavanderia-app/
├── frontend/
│   ├── login.html                  — Pantalla de inicio de sesión
│   ├── admin_dashboard.html        — Panel del administrador
│   └── empleado_dashboard.html     — Panel del empleado
├── backend/
│   ├── config.php                  — Conexión a la base de datos
│   ├── login.php                   — Autenticación de usuarios
│   ├── clientes.php                — CRUD de clientes
│   ├── pedidos.php                 — CRUD de pedidos
│   ├── sacos.php                   — CRUD de sacos de ropa
│   ├── productos.php               — CRUD de productos y stock
│   ├── facturas.php                — CRUD de facturas mensuales
│   └── usuarios.php                — CRUD de usuarios y roles
└── lavanderia_schema.sql           — Esquema completo de la base de datos
```

---

## Base de datos

La base de datos está compuesta por 12 tablas relacionales:

- **usuarios** — Personal autorizado con contraseñas cifradas en BCrypt
- **roles** — Tipos de usuario: ADMIN y EMPLEADO
- **clientes** — Datos de los clientes de la lavandería
- **pedidos** — Pedidos vinculados a clientes y empleados
- **estados_pedido** — Catálogo de estados del pedido
- **sacos** — Sacos de ropa con peso y subtotal calculado automáticamente
- **tipos_ropa** — Catálogo de tipos con precio por kg
- **facturas** — Facturas generadas con IVA automático
- **productos** — Stock de productos con alerta de mínimo
- **categorias_producto** — Agrupación de productos
- **movimientos_stock** — Historial de entradas y salidas
- **sesiones** — Control de sesiones activas

Además incluye dos vistas útiles y un procedimiento almacenado para generar facturas con numeración correlativa automática.

---

## Requisitos

- [XAMPP](https://www.apachefriends.org/) instalado en Windows
- Navegador web (Chrome, Firefox, Edge)
- [Visual Studio Code](https://code.visualstudio.com/) recomendado

---

## Instalación y ejecución paso a paso

### 1. Colocar el proyecto en XAMPP

Copia la carpeta `lavanderia-app` dentro de:
```
C:\xampp\htdocs\lavanderia-app
```

### 2. Arrancar XAMPP

Abre el panel de XAMPP y pulsa **Start** en **Apache** y en **MySQL**. Los dos tienen que estar en verde.

### 3. Crear la base de datos

Abre VS Code, instala el plugin **MySQL** de cweijan, conéctate a `localhost` con usuario `root` y sin contraseña, abre el archivo `lavanderia_schema.sql` y ejecuta **Run All SQL Statements**.

### 4. Crear los usuarios del sistema

Abre el navegador y ve a:
```
http://localhost/lavanderia-app/backend/setup.php
```
Esto crea los usuarios con las contraseñas cifradas en BCrypt. Ejecútalo solo una vez y bórralo después.

### 5. Cargar datos de ejemplo (opcional)

Si quieres ver la aplicación con datos reales desde el primer momento:
```
http://localhost/lavanderia-app/backend/datos_ejemplo.php
```
Cargará 8 clientes, 16 productos y 6 pedidos de ejemplo. Bórralo después de ejecutarlo.

### 6. Abrir la aplicación

```
http://localhost/lavanderia-app/frontend/login.html
```

---

## Credenciales de acceso

| Rol | Email | Contraseña |
|-----|-------|------------|
| Administrador | admin@lavanderia.com | Admin1234! |
| Empleado | empleado@lavanderia.com | Empleado2024! |

---

## Funcionalidades implementadas

- ✅ Login real con autenticación contra la base de datos y BCrypt
- ✅ Redirección automática según el rol del usuario
- ✅ Panel de administrador con acceso completo
- ✅ Panel de empleado con acceso restringido
- ✅ CRUD completo de clientes, pedidos, sacos, productos, facturas y usuarios
- ✅ Gestión de sacos con 10 tipos de ropa: blanca, color, delicada, ropa hogar, especial, toallas medianas, toallas grandes, alfombrines, paños de cara y baberos
- ✅ Cambio de estado de pedidos: Pendiente → En proceso → Finalizado → Entregado
- ✅ Vista de sacos del día por fecha con totales de peso e importe
- ✅ Facturación mensual automática por cliente con IVA del 21%
- ✅ Control de stock con alertas visuales cuando el producto está bajo mínimo
- ✅ Gestión de usuarios con asignación de roles desde el panel del administrador
- ✅ Base de datos relacional con 12 tablas, vistas y procedimientos almacenados

---

## Autor

**Felipe Muñoz López**
2º DAM — CampusFP
Trabajo de Fin de Grado — 2024