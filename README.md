# WordPress - AI Content Predictor Plugin

This repository contains a **Dockerized WordPress setup** for developing and maintaining the **AI Content Predictor Plugin**.

It is structured to enable **easy environment replication across laptops**, ensuring you can continue development seamlessly by simply cloning and running Docker.

---

## 📂 Project Structure


- `docker-compose.yml` – Defines the WordPress + MySQL containers.
- `html/` – Contains the entire WordPress installation with your plugin, themes, and settings.
- `php/` – For any additional scripts if needed.

---

## 🚀 Running Locally

1️⃣ **Clone the repository:**

```bash
git clone https://github.com/santuduttacc/WordPress-AI-Content-Predictor-Plugin.git
cd WordPress-AI-Content-Predictor-Plugin

2️⃣ Start the Docker environment:

docker-compose up -d

3️⃣ Access your local WordPress site:

PHP
http://wordpress661.local:8040/info.php

Admin :
http://wordpress661.local:8040/wp-admin/index.php

