<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <title>Kapcsolat – Nido Ingatlan</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

  <!-- Fejléc (opcionális, ha egységes akarsz maradni, hagyd meg az indexben használtat) -->
  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="/index.php" class="font-bold text-pink-900">nidoingatlan.hu</a>
      <nav class="text-sm flex gap-6">
        <a class="hover:text-pink-900" href="/index.php">Főoldal</a>
        <a class="hover:text-pink-900" href="/kereso.php">Kereső</a>
        <span class="text-pink-900 font-semibold">Kapcsolat</span>
      </nav>
    </div>
  </header>

  <!-- Kártya -->
  <main class="max-w-6xl mx-auto px-4 py-10">
    <div class="mx-auto w-full max-w-md">
      <article class="relative bg-white rounded-2xl shadow-2xl hover:shadow-[0_25px_50px_rgba(0,0,0,0.25)] transition overflow-hidden">
        <!-- „ikon” fejléc -->
        <div class="h-2 bg-pink-900"></div>
        <div class="p-6">
          <div class="w-12 h-12 rounded-xl bg-pink-100 flex items-center justify-center mb-4">
            <!-- kis „épület” ikon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-pink-800" viewBox="0 0 24 24" fill="currentColor">
              <path d="M4 21v-9a2 2 0 0 1 1-1.732l6-3.464a2 2 0 0 1 2 0l6 3.464A2 2 0 0 1 20 12v9h-5v-5a3 3 0 1 0-6 0v5H4z"/>
            </svg>
          </div>

          <h1 class="text-2xl font-bold text-pink-900">Kapcsolat</h1>
          <p class="text-sm text-gray-600 mt-1">Vedd fel velünk a kapcsolatot — gyorsan válaszolunk.</p>

          <div class="mt-5 space-y-3">
            <p>📧
              <a href="mailto:erika@nidoingatlan.hu" class="text-pink-900 underline">
                erika@nidoingatlan.hu
              </a>
            </p>
            <p>📞
              <a href="tel:+36204465216" class="text-pink-900 underline">
                +36 20 446 5216
              </a>
            </p>
          </div>

          <!-- gombok, mint a kártyán a „Részletek” -->
          <div class="mt-6 flex gap-3">
            <a href="mailto:erika@nidoingatlan.hu"
               class="inline-block bg-pink-900 text-white px-4 py-2 rounded-lg hover:bg-pink-950">
              Írj üzenetet
            </a>
            <a href="tel:+36204465216"
               class="inline-block border border-pink-900 text-pink-900 px-4 py-2 rounded-lg hover:bg-pink-50">
              Hívás indítása
            </a>
          </div>
        </div>
      </article>
    </div>
  </main>

</body>
</html>
