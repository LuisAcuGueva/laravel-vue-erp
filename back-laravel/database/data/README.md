# Datos versionados

## `ubigeo_inei.csv`

Fuente: catálogo de ubigeo **INEI** (código que exige SUNAT en el UBL 2.1, no RENIEC) tomado de
[jmcastagnetto/ubigeo-peru-aumentado](https://github.com/jmcastagnetto/ubigeo-peru-aumentado) (`ubigeo_distrito.csv`, columna `inei`),
con las tildes de los nombres completadas desde [ernestorivero/Ubigeo-Peru](https://github.com/ernestorivero/Ubigeo-Peru) (INEI 2016)
solo cuando el nombre coincide sin tildes. Descargado el 2026-10-16.

- 1 892 distritos, 196 provincias y 25 departamentos (Callao es el departamento `07`).
- Formato: UTF-8 sin BOM, separador coma, cabecera `code,department,province,district`, una fila por distrito,
  códigos con ceros a la izquierda, nombres en MAYÚSCULAS, ordenado por `code`.

### Cómo regenerarlo

1. Descargar el CSV fuente y normalizarlo al formato anterior (descartar filas sin código INEI).
2. Verificar: 25 prefijos de 2 dígitos, 196 de 4 dígitos, códigos `^\d{6}$` únicos y ningún nombre vacío.
3. Sembrar: `php artisan db:seed --class=UbigeoSeeder --force` (idempotente; los códigos retirados quedan `is_active = false`).
