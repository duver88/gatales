# Gatales - Guía de Configuración

## Prerrequisitos

- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 16+
- Cuenta de OpenAI con acceso a la API de Assistants

## 1. Configuración del Backend

### 1.1 Crear la base de datos

```sql
CREATE DATABASE gatales;
```

### 1.2 Configurar el archivo .env

```bash
cd backend
cp .env.example .env
```

Edita el archivo `.env` con tus credenciales:

```env
DB_DATABASE=gatales
DB_USERNAME=tu_usuario_postgres
DB_PASSWORD=tu_contraseña

OPENAI_API_KEY=sk-tu-api-key
OPENAI_ASSISTANT_ID=asst_xxx  # Lo obtendrás en el paso 2

WEBHOOK_SECRET_KEY=genera-una-clave-segura-aqui

MAIL_HOST=tu-servidor-smtp
MAIL_USERNAME=tu-usuario
MAIL_PASSWORD=tu-contraseña
MAIL_FROM_ADDRESS=noreply@tudominio.com

FRONTEND_URL=http://localhost:5173
```

### 1.3 Instalar dependencias y migrar

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 1.4 Iniciar el servidor

```bash
php artisan serve
```

El backend estará disponible en `http://localhost:8000`

## 2. Crear el OpenAI Assistant

### 2.1 Acceder a la plataforma de OpenAI

1. Ve a [platform.openai.com](https://platform.openai.com)
2. Navega a **Assistants** en el menú lateral
3. Haz clic en **Create**

### 2.2 Configurar el Assistant

**Nombre:** Gatales - Asistente de Guiones

**Instructions (Instrucciones del sistema):**

```
Eres un experto en creación de guiones de video para redes sociales y YouTube. Tu objetivo es ayudar a los usuarios a crear guiones atractivos, bien estructurados y optimizados para captar la atención del público.

Cuando un usuario te pida crear un guion:

1. Primero pregunta sobre el tema, el público objetivo y la duración aproximada del video.

2. Crea un guion estructurado con:
   - Hook (gancho inicial) - Los primeros 3-5 segundos son cruciales
   - Introducción del tema
   - Desarrollo con puntos clave
   - Llamada a la acción (CTA)
   - Cierre memorable

3. Incluye indicaciones de:
   - Tono de voz
   - Momentos para insertar B-roll o gráficos
   - Pausas dramáticas
   - Énfasis en palabras clave

4. Ofrece variaciones del hook si el usuario lo solicita.

5. Adapta el lenguaje según la plataforma (YouTube, TikTok, Instagram Reels, etc.)

Siempre mantén un tono amigable pero profesional. Si el usuario tiene dudas sobre el formato, explícale las mejores prácticas para ese tipo de contenido.
```

**Model:** gpt-4-turbo (o gpt-4o si está disponible)

**Tools:**
- Activa **File Search** si quieres subir documentos de referencia

### 2.3 Guardar el ID del Assistant

Una vez creado, copia el ID del Assistant (formato: `asst_xxxxx`) y agrégalo a tu archivo `.env`:

```env
OPENAI_ASSISTANT_ID=asst_tu_id_aqui
```

### 2.4 (Opcional) Subir archivos de conocimiento

Si tienes PDFs o documentos con información adicional para el asistente:

1. En la configuración del Assistant, ve a **Files**
2. Sube tus documentos
3. El asistente los usará como referencia al responder

## 3. Configuración del Frontend

### 3.1 Instalar dependencias

```bash
cd frontend
npm install
```

### 3.2 Configurar variables de entorno

El archivo `.env` ya está configurado. Si el backend está en otro puerto:

```env
VITE_API_URL=http://localhost:8000/api
```

### 3.3 Iniciar el servidor de desarrollo

```bash
npm run dev
```

El frontend estará disponible en `http://localhost:5173`

## 4. Configuración de n8n (Webhooks)

### 4.1 Importar el workflow

1. Abre tu instancia de n8n
2. Ve a **Workflows** → **Import from file**
3. Importa el archivo `backend/n8n-workflow-hotmart.json`

### 4.2 Configurar variables de entorno en n8n

En la configuración de n8n, agrega estas variables:

```
GATALES_API_URL=https://tu-dominio.com
WEBHOOK_SECRET=tu-misma-clave-del-env
```

### 4.3 Obtener la URL del webhook

1. Abre el workflow importado
2. Haz clic en el nodo **Hotmart Webhook**
3. Copia la URL de producción

### 4.4 Configurar Hotmart

1. En tu panel de Hotmart, ve a **Configuraciones** → **Webhooks**
2. Agrega la URL de n8n como destino
3. Selecciona los eventos:
   - PURCHASE_COMPLETE
   - PURCHASE_APPROVED
   - PURCHASE_CANCELED
   - PURCHASE_REFUNDED
   - SUBSCRIPTION_CANCELLATION

## 5. Acceso al Panel de Admin

Credenciales por defecto:
- **Email:** admin@gatales.com
- **Contraseña:** admin123

⚠️ **IMPORTANTE:** Cambia la contraseña del admin inmediatamente después del primer acceso.

Para cambiarla, usa Laravel Tinker:

```bash
php artisan tinker
>>> $admin = App\Models\Admin::first();
>>> $admin->password = Hash::make('tu-nueva-contraseña');
>>> $admin->save();
```

## 6. Comandos Útiles

### Ejecutar colas (para envío de emails)

```bash
php artisan queue:work
```

### Limpiar caché

```bash
php artisan cache:clear
php artisan config:clear
```

### Ver logs

```bash
tail -f backend/storage/logs/laravel.log
```

## 7. Estructura de Carpetas

```
gatales/
├── backend/                 # API Laravel
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Mail/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/api.php
│
├── frontend/                # Vue.js SPA
│   ├── src/
│   │   ├── components/
│   │   ├── views/
│   │   ├── stores/
│   │   ├── services/
│   │   └── router/
│   └── tailwind.config.js
│
└── SETUP.md                 # Esta guía
```

## Soporte

Si tienes problemas durante la configuración, verifica:

1. Los logs del backend: `backend/storage/logs/laravel.log`
2. La consola del navegador para errores del frontend
3. Los logs de n8n para problemas con webhooks
4. El dashboard de OpenAI para verificar el uso de la API
