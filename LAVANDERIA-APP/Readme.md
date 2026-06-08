# 🧺 LavanderApp

**Sistema de Gestión de Lavandería Profesional**  
Aplicación web Full Stack con Inteligencia Artificial integrada

> Trabajo de Fin de Grado — 2º DAM · CampusFP · Felipe Muñoz López · 2025-2026

---

## 📋 Descripción

LavanderApp digitaliza y automatiza todos los procesos de una lavandería profesional: recepción de pedidos con múltiples sacos, seguimiento de estados, facturación automática con IVA, control de stock e inteligencia artificial conversacional con datos en tiempo real.

---

## 🚀 Tecnologías

| Capa | Tecnología |
|------|-----------|
| Frontend | HTML5, CSS3, JavaScript ES6+ (Vanilla) |
| Backend | PHP 8.2 — API REST |
| Base de datos | MySQL / MariaDB 10.4 |
| Gráficas | Chart.js 4.4 |
| IA | Groq API + LLaMA 3.3 70B Versatile |
| Seguridad | BCrypt + Prepared Statements |
| Servidor | Apache (XAMPP) |

---

## 📁 Estructura del proyecto

```
lavanderia-app/
├── frontend/
│   ├── login.html                  # Pantalla de login
│   ├── admin_dashboard.html        # Panel administrador
│   └── empleado_dashboard.html     # Panel empleado
├── backend/
│   ├── config.php                  # Conexión a BD
│   ├── login.php                   # Autenticación BCrypt
│   ├── clientes.php                # CRUD clientes + borrado en cascada
│   ├── pedidos.php                 # CRUD pedidos + borrado en cascada
│   ├── sacos.php                   # CRUD sacos
│   ├── facturas.php                # Facturación completa
│   ├── productos.php               # CRUD productos + alertas stock
│   ├── usuarios.php                # CRUD usuarios + BCrypt
│   ├── asistente.php               # IA Groq con contexto BD en tiempo real
│   ├── exportar_facturas.php       # Exportar Excel mensual/individual
│   ├── exportar_pdf.php            # Exportar PDF individual de factura
│   └── setup.php                   # Crear usuarios iniciales (ejecutar 1 vez)
└── lavanderia_schema.sql           # Script completo de creación de BD
```

---

## ⚙️ Instalación

### Requisitos
- XAMPP (Apache + PHP 8.0+ + MySQL)
- Extensiones PHP: `mysqli`, `curl`
- Navegador moderno con soporte ES6+

### Pasos

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/felipeml/lavanderia-app.git
   ```

2. **Copiar a htdocs**
   ```
   C:\xampp\htdocs\lavanderia-app\
   ```

3. **Iniciar XAMPP** — arrancar Apache y MySQL

4. **Crear la base de datos**
   - Abrir `http://localhost/phpmyadmin`
   - Crear base de datos `lavanderia` con charset `utf8mb4_unicode_ci`
   - Importar `lavanderia_schema.sql`

5. **Crear usuarios iniciales**
   ```
   http://localhost/lavanderia-app/backend/setup.php
   ```

6. **Acceder a la aplicación**
   ```
   http://localhost/lavanderia-app/frontend/login.html
   ```

---

## 🤖 Configurar el Asistente IA

1. Crear cuenta gratuita en [console.groq.com](https://console.groq.com)
2. Generar una API Key en el panel de Groq
3. Abrir `backend/asistente.php` y sustituir:
   ```php
   $GROQ_KEY = 'TU_API_KEY_AQUI';
   ```

---

## 🔑 Credenciales de acceso

| Rol | Email | Contraseña |
|-----|-------|-----------|
| Administrador | admin@lavanderia.com | Admin1234! |
| Empleado | empleado@lavanderia.com | Empleado2024! |

---

## ✨ Funcionalidades principales

### Panel Administrador
- **Panel general** — tarjetas interactivas, gráficas Chart.js, alertas IA automáticas
- **Clientes** — CRUD + buscador en tiempo real + borrado en cascada
- **Pedidos** — multi-saco, filtros, avanzar estado, editar, eliminar
- **Sacos** — registro de múltiples sacos por cliente con tipo y peso
- **Facturación** — estilo Odoo con estados (Borrador → Confirmada → Pagada/Cancelada), selección multi-pedido, cálculo automático de IVA, exportar Excel y PDF
- **Productos** — CRUD + alertas de stock bajo
- **Usuarios** — CRUD + baja lógica + eliminar permanente + BCrypt
- **Mi perfil** — editar datos + cambiar contraseña
- **Asistente IA** — chat conversacional con datos reales de la BD

### Panel Empleado
- **Panel general** — tarjetas, gráficas del día, alertas de stock
- **Nuevo pedido** — selección de sacos existentes del cliente + opción nuevos sacos
- **Pedidos** — buscador, filtros, avanzar estado, ver sacos, editar
- **Sacos del día** — vista por fecha, añadir múltiples sacos
- **Clientes** — CRUD + buscador
- **Productos** — vista solo lectura con alertas
- **Mi perfil** — editar datos + cambiar contraseña
- **Asistente IA** — mismo chat que admin

---

## 🗄️ Base de datos

12 tablas: `usuarios`, `roles`, `clientes`, `pedidos`, `estados_pedido`, `sacos`, `tipos_ropa`, `facturas`, `productos`, `categorias_producto`, `movimientos_stock`, `sesiones`

**Característica destacada:** la columna `sacos.subtotal` es `GENERATED ALWAYS AS (peso_kg * precio_kg)` — se calcula automáticamente en la BD.

---

## 🔒 Seguridad

- Contraseñas hasheadas con **BCrypt** (nunca en texto plano)
- Validación de sesión al cargar cada dashboard — sin sesión redirige a login
- **Prepared Statements** en todas las consultas SQL (previene inyección SQL)
- Control de acceso por rol — admin y empleado tienen interfaces separadas

---

## 📄 Licencia

Proyecto académico — TFG 2º DAM CampusFP 2025-2026  
Felipe Muñoz López