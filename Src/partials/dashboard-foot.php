<?php
/*
 * Shared bottom of every dashboard page: closes the layout opened by
 * dashboard-head.php, lists the sections hidden because they are empty,
 * and loads the scripts.
 */

?>
         <div id="empty_sections_summary" class="empty-sections-summary"></div>
      </div>
   </div>

   <?php require_once "footer.php"; ?>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
   <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"
      integrity="sha384-Mx5lkUEQPM1pOJCwFtUICyX45KNojXbkWdYhkKUKsbv391mavbfoAmONbzkgYPzR"
      crossorigin="anonymous"></script>
   <script src="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js"
      integrity="sha384-y62I+ZvjNRolkugL/AMpUZykqrL6oqYxBruObmlDAhDpmapM0s+xgvVK+wGVpza0"
      crossorigin="anonymous"></script>
   <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
      integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs"
      crossorigin="anonymous"></script>
   <script src="https://cdn.jsdelivr.net/npm/jquery-confirm@3.3.4/js/jquery-confirm.min.js"
      integrity="sha384-+p7/5C/2VUHNat/iYiavlLtoVDvsaFOOt2fQO2w0AUNc4v0iu4S84sgkM+5/a+TI"
      crossorigin="anonymous"></script>
   <script type="module" src="static/main.js?<?php echo filemtime("static/main.js"); ?>"></script>
   <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
   <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(function (OneSignal) {
         OneSignal.init({
            appId: "90c57079-9ef2-4719-bddf-361e6510de17",
            googleProjectNumber: "256841700684"
         });
      });
   </script>
</body>

</html>
