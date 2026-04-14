# CRM — Guía para Claude

Esta aplicación es un **CRM SaaS multiempresa** desarrollado desde cero para unificar dos soluciones existentes en una única plataforma.

## Objetivo del proyecto

El objetivo de esta aplicación es centralizar en una sola solución:

- La lectura y gestión de **oportunidades** recibidas por email
- La gestión de **ofertas** presentadas
- El registro de **competidores**
- La valoración **cualitativa y cuantitativa** de las ofertas

La aplicación sustituirá progresivamente a las dos soluciones actuales.
Se parte de un **modelo nuevo y limpio**, sin necesidad de mantener compatibilidad con estructuras o nombres antiguos.

## Alcance funcional inicial

La primera versión debe cubrir como mínimo:

- **Oportunidades**
- **Ofertas**
- **Competidores**
- **Valoraciones de competidores y ofertas**

Las dos aplicaciones anteriores son casi idénticas, por lo que la nueva aplicación debe diseñarse como una unificación funcional, no como una simple copia técnica.

---

## Stack técnico

- **Laravel 11+**
- **Filament** para uso interno / panel administrativo
- **Livewire 3**
- **Alpine.js**
- **Tailwind CSS**
- **MySQL**
- Posible uso de **Docker** en desarrollo y eventualmente también en producción
- API pública e integraciones externas desde el inicio

## Finalidad de Filament

Filament se usa como **panel interno y de gestión**.
No se debe asumir que la aplicación está pensada como un panel genérico de administración sin reglas de negocio.
La lógica del dominio debe mantenerse clara y separada.

---

## Naturaleza SaaS y multitenancy

Esta aplicación es **multiempresa** con una única base de datos compartida.

### Regla principal de tenancy

Todos los registros de negocio deben pertenecer a una empresa mediante:

- `id_company`

### Regla obligatoria

Cualquier entidad de negocio debe quedar ligada a una empresa, salvo que exista una razón técnica muy justificada para que sea global.

### Aislamiento de datos

Cada empresa solo puede acceder a sus propios datos.

Claude debe asumir siempre que:

- toda consulta debe estar filtrada por `id_company`
- toda creación debe asignar `id_company`
- toda edición o borrado debe validar la pertenencia a la empresa activa del usuario
- nunca deben mostrarse, agregarse ni relacionarse datos de distintas empresas por error

### Prohibiciones

- No hacer queries sin scope de empresa en modelos de negocio
- No usar selects globales “rápidos” para pruebas que luego queden en producción
- No asumir que un registro es accesible solo por conocer su `id`
- No construir relaciones o métricas cruzando empresas salvo que sea una funcionalidad explícitamente administrativa y segura

---

## Modelo de usuarios, seguridad y autenticación

La gestión de seguridad **no vive en CRM**.

Existe otra aplicación externa, llamada internamente **StockFlow / Core**, que gestiona:

- usuarios
- roles
- aplicaciones
- proyectos
- compañías

Los usuarios se dan de alta allí y CRM hereda esa información.

### Consecuencia para Claude

CRM **no debe convertirse en el sistema maestro de identidad**.

Por defecto, Claude debe asumir que:

- los usuarios ya existen externamente
- la empresa del usuario ya viene definida externamente
- los roles vienen heredados o sincronizados desde el sistema Core
- CRM solo consume o refleja esta información para aplicar permisos y comportamiento

### Regla de diseño

No duplicar innecesariamente lógica de identidad, gestión de usuarios o seguridad que ya pertenezca a Core.

Si CRM necesita datos locales auxiliares del usuario, deben estar claramente separados de la identidad maestra.

---

## Roles y comportamiento por empresa

- Un usuario pertenece a **una sola empresa**
- Los roles son **por empresa**
- La aplicación es la misma para todos los clientes
- El comportamiento cambia según la empresa a la que pertenezca el usuario

Claude debe priorizar un diseño en el que:

- la aplicación sea única
- la personalización sea por configuración
- no se duplique lógica por empresa
- no existan ramas de código paralelas salvo necesidad real

---

## Personalización por empresa

Cada empresa puede personalizar:

- **logo**
- **colores**
- algunos **valores de enum** o configuraciones equivalentes de ciertos campos
- conexión con su propio **tablero Kanboard**

### Gestión de esta personalización

La configuración debe gestionarse desde una opción de **administración**.

### Regla de diseño

La personalización debe resolverse mediante configuración y metadatos, no mediante forks de código.

### Qué evitar

- `if company_id == X` dispersos por toda la aplicación
- vistas duplicadas por empresa
- lógica de negocio distinta metida directamente en componentes Livewire o Blade
- enums rígidos cuando una parte del catálogo de valores debe ser configurable por empresa

### Qué favorecer

- tablas/configuración por empresa
- servicios de branding
- configuración centralizada
- catálogos configurables por empresa para los casos permitidos

---

## Integración con Kanboard

Cada empresa tendrá conexión con **un tablero distinto de Kanboard**.

Claude debe asumir que esta integración:

- es por empresa
- requiere configuración por empresa
- debe resolverse a través de servicios o adaptadores de integración
- no debe acoplar la lógica del CRM directamente al cliente HTTP o a detalles de infraestructura

---

## Módulos de dominio iniciales

## 1. Oportunidades

Representan oportunidades detectadas desde emails u otras fuentes de entrada.

Capacidades esperadas:

- almacenar la oportunidad
- registrar su estado
- enlazar con la empresa propietaria
- permitir evolución hacia oferta
- mantener trazabilidad básica del ciclo

## 2. Ofertas

Representan las ofertas preparadas o presentadas respecto a una oportunidad.

Capacidades esperadas:

- guardar detalle de la oferta
- enlazar con la oportunidad
- almacenar importes, criterios u otros campos relevantes
- registrar la evolución del proceso comercial

## 3. Competidores

Permiten registrar los competidores que concurren en una oportunidad o licitación.

Capacidades esperadas:

- asociar competidores a una oportunidad y/o oferta
- almacenar información identificativa
- almacenar datos comparativos relevantes

## 4. Valoraciones

Permiten registrar valoración cualitativa y cuantitativa de ofertas y competidores.

Capacidades esperadas:

- puntuaciones
- observaciones cualitativas
- criterios configurables si aplica
- trazabilidad por empresa

---

## Origen de datos legacy (sincronización)

Los datos reales de las dos soluciones previas viven en el **mismo host** que se ha dado de alta para Kanboard, distribuidos en **dos bases de datos distintas**:

- **BD de gestión** → datos de **Autoritas**
- **BD de absolute** → datos de **Absolute**

### Tablas relevantes en ambas BDs

- `infonaliadata` → **leads / oportunidades** (presente en las dos BDs)
- `cial_ofertas` → **ofertas** (cabecera)
- `cial_ofertas_has_details` → detalles de oferta (necesario de entrada)
- `cial_ofertas_has_dates` → fechas asociadas a la oferta (necesario de entrada)

### Implicaciones para la sincronización

- Al traer datos hay que **fusionar las dos BDs** asignando correctamente el `id_company` correspondiente (Autoritas vs Absolute) para respetar el aislamiento multiempresa.
- La importación debe ser **idempotente** y encapsulada en servicios / acciones (no scripts sueltos dispersos).
- El esquema legacy (`cial_*`, `infonaliadata`) **no** debe filtrarse al modelo nuevo: se mapea a las entidades limpias del CRM (Oportunidades, Ofertas, etc.).

---

## Filosofía de arquitectura

Claude debe priorizar una arquitectura clara, mantenible y evolutiva.

### Reglas recomendadas

- Modelos Eloquent enfocados a persistencia y relaciones
- Reglas de negocio importantes fuera de Blade y fuera de Resources de Filament
- Servicios / acciones para operaciones de dominio
- Validación mediante Form Requests o clases equivalentes cuando aplique
- Políticas y autorización explícita
- Integraciones externas encapsuladas en servicios
- Configuración multiempresa centralizada

### Evitar

- lógica compleja directamente en Resources de Filament
- reglas de negocio ocultas en componentes Livewire
- uso excesivo de helpers globales
- controladores gigantes
- duplicación de lógica entre panel, API e integraciones

---

## Estructura sugerida del proyecto

Si no existe una decisión más específica, Claude debe tender hacia una estructura parecida a esta:

```text
app/
├── Actions/                 # Casos de uso / acciones de dominio
├── DTOs/                    # Objetos de transporte de datos cuando aporten claridad
├── Enums/                   # Enums del sistema (solo donde tenga sentido)
├── Filament/
│   ├── Resources/
│   └── Pages/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Integrations/
│   ├── Core/
│   └── Kanboard/
├── Models/
├── Policies/
├── Services/
├── Support/
├── Tenancy/
└── Traits/
