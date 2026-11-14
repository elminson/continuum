# Speaker script — Sunday 16:00

About 4 minutes plus a 1-minute live demo. Two speakers: **E** = Elminson, **M** = Miguel (swap as you like). Spanish first (local judges), English below. Have the deck open in present mode and a second tab on https://continuum.techspark.dev/alerts/193823 (Cuba) and https://continuum.techspark.dev/regions/hispaniola.

---

## Español

**Diapositiva 1 — Portada (20 s) · E**
Buenas tardes. Somos Elminson y Miguel, el equipo Continuum, de Santo Domingo. Elegimos el reto de armonizar los focos de calor de MODIS y VIIRS. En una frase: convertimos 26 años de datos de fuego de la NASA en un solo calendario en el que se puede confiar, para cualquier área del planeta.

**Diapositiva 2 — El problema (40 s) · M**
La NASA vigila incendios desde el espacio desde el año 2000 con MODIS, con píxeles de un kilómetro. En 2012 llegó VIIRS, con píxeles de 375 metros, que ve muchos más fuegos pequeños.
Miren la gráfica: la línea naranja es VIIRS. En La Española ve unas ocho veces más fuego que MODIS. Si alguien simplemente junta los dos registros, parece que en 2012 empezó una ola de incendios. No fue así: fue un satélite nuevo. Un bombero o un gestor de emergencias no puede comparar años con un registro así.

**Diapositiva 3 — Cómo lo resolvemos (50 s) · E**
Separamos el problema en tres partes.
Uno: contamos celdas de un kilómetro con fuego por día, no píxeles. Así un frente de fuego cuenta una vez para los dos sensores.
Dos: unimos todos los satélites de cada sensor, para que tener más satélites no infle el conteo.
Tres: la diferencia de sensibilidad, que es física, la modelamos con dos funciones de transferencia transparentes, ajustadas con los 14 años en que ambos sensores volaron juntos.
Y lo comprobamos de forma honesta: reservamos uno de cada cuatro días y medimos el error contra la mezcla ingenua. El error baja entre 76 y 89 por ciento en nuestras cuatro áreas. La app también muestra dónde funciona peor.

**Diapositiva 4 — El calendario (30 s) · M**
Esto es lo que ve el usuario. Cada fila es un año, cada celda un mes. En La Española, marzo resalta todos los años: la temporada de fuego va de febrero a abril.
El usuario elige un área o dibuja la suya, escoge las fechas, compara con la mediana de 25 años y descarga todo: gráficos, calendario y los focos en CSV. En español e inglés.

**Diapositiva 5 — Alerta temprana (40 s) · E**
Y lo más importante: alerta temprana. Continuum compara cada semana con la misma semana de todos los demás años, en una sola escala.
En la semana del 17 de agosto de 2026 marcó a Cuba en 21 veces lo normal, lo más alto del registro. Sitios de monitoreo reportaron varios incendios forestales en Cuba esos mismos días. Esto solo se puede ver con un registro armonizado.

**Demo en vivo (60 s) · M** — cambiar a la pestaña del navegador
- Abrir la página de Cuba (alerta): señalar el mapa de dónde estuvo el fuego y el ×21,4.
- Abrir La Española: pulsar "Todos los años", señalar el calendario y la comparación con la mediana.
- Pulsar "CSV de focos de calor" para mostrar la descarga.
Si la red falla: seguir con las diapositivas, ya muestran lo mismo.

**Diapositiva 6 — Datos de la NASA (25 s) · M**
Todo viene de datos abiertos de la NASA: FIRMS, con MODIS y VIIRS, más de 26 millones de detecciones, e imágenes GIBS para los mapas. El sitio se actualiza cada hora. Cuando el satélite Suomi NPP dejó de enviar datos el 2 de noviembre, pasamos a NOAA-20 y NOAA-21. El código es abierto y tiene una API pública.

**Diapositiva 7 — Lo que sigue (25 s) · E**
Queremos que cualquier agencia de incendios del Caribe pueda apuntar Continuum a su territorio, empezando por República Dominicana y Haití. Y hay prisa: la NASA planea apagar MODIS hacia enero de 2027, en unos dos meses. Ya lo probamos: simulamos el apagado y, solo con VIIRS, Continuum mantiene la escala de 26 años con un error diario entre 62 y 89 por ciento menor que un cambio ingenuo.
Pueden probarlo ahora mismo en continuum.techspark.dev; el acceso para jueces está en la pantalla. Muchas gracias.

### Preguntas probables
- **¿Por qué confiar en la armonización?** Se valida con más de 1.200 días reservados por área, y cada exportación lleva su "recibo del método": fuentes, factores, intervalo y error.
- **¿Qué pasa con las nubes?** Los datos de fuego activo solo dicen dónde hubo fuego. Lo indicamos en la sección de limitaciones; el siguiente paso son máscaras de nubes por celda.
- **¿Usaron IA?** Sí: como asistente de programación, para la voz del video y para redactar las diapositivas y los textos, y lo declaramos. Hay una lectura de IA opcional en las alertas, marcada como orientativa: la estadística detecta el periodo y el modelo solo lo explica.
- **¿Lo hicieron durante el evento?** La mayor parte de la app la construimos antes, desde septiembre; lo explicamos con fechas en docs/BUILD-TIMELINE.md. Este fin de semana preparamos las áreas, la presentación y los videos.
- **¿Cuándo se apaga MODIS?** Según la NASA, Aqua alrededor de agosto–septiembre de 2026 y Terra hacia enero de 2027; hoy ambos siguen enviando datos. En la página Lab de cada área, "Cuando MODIS se retire" muestra la simulación.
- **¿Funciona en otra región?** Sí: cualquier usuario dibuja un recuadro y el sistema descarga y armoniza su historia completa.

---

## English

**Slide 1 — Cover (20 s) · E**
Good afternoon. We're Elminson and Miguel, team Continuum, from Santo Domingo. Our challenge is harmonizing MODIS and VIIRS hot spots. In one sentence: we turn 26 years of NASA fire data into one calendar you can trust, for any area on Earth.

**Slide 2 — The problem (40 s) · M**
NASA has watched fires from space since 2000 with MODIS, at one kilometre. In 2012 VIIRS arrived at 375 metres and sees many more small fires.
Look at the chart: the orange line is VIIRS. On Hispaniola it sees about eight times more fire than MODIS. Stack the two records and it looks like a fire boom started in 2012. It didn't; a new satellite did. A fire manager can't compare years with a record like that.

**Slide 3 — How we fix it (50 s) · E**
We split the problem in three.
One: we count one-kilometre grid cells with fire per day, not pixels, so one fire front counts once for both sensors.
Two: we merge every satellite of each sensor, so more satellites don't inflate the count.
Three: the sensitivity gap is physics, so we model it with two transparent transfer functions fitted on the 14 years both sensors flew.
And we test it honestly: we hold out every fourth day and measure the error against a naive merge. The error drops by 76 to 89 percent on our four areas, and the app also shows where it works worst.

**Slide 4 — The calendar (30 s) · M**
This is what the user sees. Each row is a year, each cell a month. On Hispaniola, March stands out every year: the fire season runs February to April.
Users pick an area or draw their own, set dates, compare with the 25-year median, and download everything: charts, the calendar and the hotspots as CSV. In English and Spanish.

**Slide 5 — Early warning (40 s) · E**
Most important: early warning. Continuum compares every week with the same week in all other years, on one scale.
In the week of 17 August 2026 it flagged Cuba at 21 times normal, the highest in the record. Fire trackers reported several wildfires across Cuba on those same days. You can only see that with a harmonized record.

**Live demo (60 s) · M** — switch to the browser tab
- Open the Cuba alert page: point at the fire map and the ×21.4.
- Open Hispaniola: click "All years", point at the calendar and the median comparison.
- Click "Hotspots CSV" to show the download.
If the network fails: stay on the slides; they show the same thing.

**Slide 6 — NASA data (25 s) · M**
Everything comes from NASA open data: FIRMS, with MODIS and VIIRS, more than 26 million detections, and GIBS imagery for the maps. The site updates every hour. When Suomi NPP stopped sending data on 2 November, we moved to NOAA-20 and NOAA-21. The code is open source with a public API.

**Slide 7 — What's next (25 s) · E**
We want any fire agency in the Caribbean to point Continuum at its own territory, starting with the Dominican Republic and Haiti. And it's urgent: NASA plans to shut down MODIS around January 2027, about two months from now. We already tested it: we simulate the shutdown, and with VIIRS alone Continuum keeps the 26-year scale with a daily error 62 to 89 percent lower than a naive switch.
You can try it right now at continuum.techspark.dev; the judge login is on the screen. Thank you.

### Likely questions
- **Why trust the harmonization?** It's validated on more than 1,200 held-out days per area, and every export carries a method receipt: sources, factors, interval and error.
- **What about clouds?** Active-fire data only says where fire was seen. We state that in the limitations; per-cell cloud masks are the next step.
- **Did you use AI?** Yes: as a coding assistant, for the video voice, and to draft the slides and texts, and we disclosed it. There's an optional AI reading on alerts, labelled advisory: the statistics flag the period, the model only explains it.
- **Did you build it during the event?** Most of the app was built before, from September; docs/BUILD-TIMELINE.md gives the dates. This weekend we prepared the areas, the presentation and the videos.
- **When does MODIS shut down?** NASA plans Aqua around August–September 2026 and Terra around January 2027; both are still sending data today. Each area's Lab page, "When MODIS retires", shows the simulation.
- **Does it work elsewhere?** Yes: any user draws a box and the system fetches and harmonizes its full history.
