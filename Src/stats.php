<?php
$activePage = "stats";
$pageTitle  = "Stats";
require_once "partials/dashboard-head.php";
?>
      <div class="stats-section">
         <div class="stats-left">
            <div class="github-stats">
               <div>
                  <img id="gh_stats" alt="GH Stats" src="" />
                  <img id="gh_streak" alt="GH Streak" src="" />
               </div>
            </div>
         </div>
         <div class="stats-right">
            <div class="wakatime-postman">
               <a href="https://wakatime.com/@6be975b7-7258-4475-bc73-9c0fc554430e" target='_blank'
                  rel='noopener noreferrer'>
                  <img id="wakatime" alt="Wakatime stats" src="" />
               </a>
               <div id="postman"></div>
            </div>
         </div>
      </div>

      <div class="pie-charts">
         <div class="pie-chart">
            <div id="pie_chart_1"></div>
         </div>
         <div class="pie-chart">
            <div id="pie_chart_2"></div>
         </div>
      </div>
<?php
require_once "partials/dashboard-foot.php";
