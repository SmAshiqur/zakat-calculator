<?php
/**
 * Plugin Name: Zakat Calculator
 * Plugin URI: https://masjidsolutions.net/
 * Description: A plugin to calculate zakat
 * Author: MasjidSolutions
 * Version: 1.0.1
 * Author URI: https://masjidsolutions.net/
 * GitHub Plugin URI: https://github.com/SmAshiqur/zakat-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Version constant to match plugin header
define( 'ZC_VERSION', '1.0.0' );
define( 'ZC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require 'lib/plugin-update-checker-master/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/SmAshiqur/zakat-calculator',
    __FILE__,
    'zakat-calculator'
);

// Set the branch (use 'main' or 'master')
$updateChecker->setBranch('main');

// Enable GitHub release assets (if used)
$updateChecker->getVcsApi()->enableReleaseAssets();



// Move conversion functions outside the shortcode function to prevent redeclaration
if (!function_exists('convertXAUtoUSDPerGram')) {
  function convertXAUtoUSDPerGram($xauRate)
  {
    if ($xauRate <= 0) {
      return "Invalid XAU rate.";
    }

    $gramsPerOunce = 31.1035;
    $usdPerOunce = 1 / $xauRate;
    $usdPerGram = $usdPerOunce / $gramsPerOunce;
    return round($usdPerGram, 2);
  }
}

if (!function_exists('convertXAGtoUSDPerGram')) {
  function convertXAGtoUSDPerGram($xagRate)
  {
    if ($xagRate <= 0) {
      return "Invalid XAG rate.";
    }

    $gramsPerOunce = 31.1035;
    $usdPerOunce = 1 / $xagRate;
    $usdPerGram = $usdPerOunce / $gramsPerOunce;
    return round($usdPerGram, 2);
  }
}

// Admin panel functions
function zakat_calculator_register_settings() {
  // Register settings
  register_setting('zakat_calculator_settings', 'zakat_calculator_donation_url', 'sanitize_url');
  register_setting('zakat_calculator_settings', 'zakat_calculator_api_key', 'sanitize_text_field');
  register_setting('zakat_calculator_settings', 'zakat_calculator_policy_url', 'sanitize_url');
}
add_action('admin_init', 'zakat_calculator_register_settings');

// Add the options page
function zakat_calculator_add_admin_menu() {
  add_options_page(
    'Zakat Calculator Settings',       // Page title
    'Zakat Calculator',                // Menu title
    'manage_options',                  // Capability
    'zakat-calculator',                // Menu slug
    'zakat_calculator_options_page'    // Callback function
  );
}
add_action('admin_menu', 'zakat_calculator_add_admin_menu');

// Admin panel page content
function zakat_calculator_options_page() {
  ?>
  <div class="wrap">
    <h1>Zakat Calculator Settings</h1>
    <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
      <h2>How to Use the Shortcode</h2>
      <p>To display the Zakat Calculator on your website, simply add the following shortcode to any page or post:</p>
      <code style="background: #f0f0f0; padding: 10px; display: inline-block; margin: 10px 0;">[zakat_calculator]</code>
    </div>
    
    <form method="post" action="options.php">
      <?php settings_fields('zakat_calculator_settings'); ?>
      <?php do_settings_sections('zakat_calculator_settings'); ?>
      
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Donation URL</th>
          <td>
            <input type="url" name="zakat_calculator_donation_url" style="width: 100%; max-width: 500px;" 
                  value="<?php echo esc_url(get_option('zakat_calculator_donation_url', '')); ?>" />
            <p class="description">Enter the URL where users will be directed when they click the "Donate your Zakat Now" button.</p>
          </td>
        </tr>
        
        <!-- <tr valign="top">
          <th scope="row">OpenExchangeRates API Key</th>
          <td>
            <input type="text" name="zakat_calculator_api_key" style="width: 100%; max-width: 500px;" 
                  value="<?php //echo esc_attr(get_option('zakat_calculator_api_key', '9ce061f709244a36a463ee6bb7581e07')); ?>" />
            <p class="description">Enter your OpenExchangeRates API key for gold and silver prices. 
              <a href="https://openexchangerates.org/signup" target="_blank">Get your API key</a>.</p>
          </td>
        </tr> -->
        
        <!-- <tr valign="top">
          <th scope="row">Zakat Policy URL</th>
          <td>
            <input type="url" name="zakat_calculator_policy_url" style="width: 100%; max-width: 500px;" 
                  value="<?php //echo esc_url(get_option('zakat_calculator_policy_url', '')); ?>" />
            <p class="description">Enter the URL to your Zakat Policy page (optional).</p>
          </td>
        </tr> -->
      </table>
      
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

function zakat_calculator_enqueue_assets()
{
  wp_enqueue_style(
    'zakat-calculator-style', // Handle
    plugin_dir_url(__FILE__) . 'css/style.css', // Path to CSS file
    array(), // Dependencies (empty array if none)
    '1.0.0' // Version number
  );
}

add_action('wp_enqueue_scripts', 'zakat_calculator_enqueue_assets');

function zakat_calculator_shortcode()
{
  ob_start();
  $donation_url = get_option('zakat_calculator_donation_url', '#');
  $policy_url = get_option('zakat_calculator_policy_url', '#');
  $api_key = get_option('zakat_calculator_api_key', '9ce061f709244a36a463ee6bb7581e07');
?>



  <?php
  $app_id = '9ce061f709244a36a463ee6bb7581e07';
  $oxr_url = "https://openexchangerates.org/api/latest.json?app_id=" . $app_id;

  // Open CURL session:
  $ch = curl_init($oxr_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  // Get the data:
  $json = curl_exec($ch);
  curl_close($ch);

  // Decode JSON response:
  $oxr_latest = json_decode($json);

  $xagRate = $oxr_latest->rates->XAG; // Replace this with the value from the API
  $silverPricePerGram = convertXAGtoUSDPerGram($xagRate);

  $xauRate = $oxr_latest->rates->XAU; // Replace this with the value from the API
  $goldPricePerGram = convertXAUtoUSDPerGram($xauRate);
  $current_date = date('jS F, Y', $oxr_latest->timestamp);
  ?>
  
  <div class="zkt-calc__wrapper">
    <!-- <div class="zkt-calc__header">
      <h1 class="zkt-calc__title">CALCULATE ZAKAT</h1>
      <h4 class="zkt-calc__subtitle">
        Enter your assets and liabilities and easily calculate your Zakat obligation below.
      </h4>
      <a href="" class="zkt-calc__policy-link">Zakat Policy</a>
    </div> -->
    
    <div class="zkt-calc__container">
      <div class="zkt-calc__form-container">
        <!-- Assets Section -->
        <div class="zkt-calc__section">
          <div class="zkt-calc__section-header">
            <h2 class="zkt-calc__section-title">Assets</h2>
            <h5 class="zkt-calc__section-subtitle">
              Assets you own on which Zakat is payable
              <span class="zkt-calc__tooltip">
                <span class="zkt-calc__tooltip-icon">i</span>
                <span class="zkt-calc__tooltip-text">These are assets that have been in your possession for one lunar year and meet the minimum nisab threshold.</span>
              </span>
            </h5>
          </div>
          
          <div class="zkt-calc__fields-grid">
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="cash-at-home">Cash at Home</label>
              <input type="number" name="cash-at-home" id="cash-at-home" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="cash-in-bank">Cash in Bank</label>
              <input type="number" name="cash-in-bank" id="cash-in-bank" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="cash-in-business">Cash in Business</label>
              <input type="number" name="cash-in-business" id="cash-in-business" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="gold">
                Gold
                <span class="zkt-calc__tooltip">
                  <span class="zkt-calc__tooltip-icon">i</span>
                  <span class="zkt-calc__tooltip-text">Price per gram $<?= $goldPricePerGram ?> (as of <?= $current_date ?>)</span>
                </span>
              </label>
              <input type="number" name="gold" id="gold" placeholder="Enter Weight in Grams" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="silver">
                Silver
                <span class="zkt-calc__tooltip">
                  <span class="zkt-calc__tooltip-icon">i</span>
                  <span class="zkt-calc__tooltip-text">Price per gram $<?= $silverPricePerGram ?> (as of <?= $current_date ?>)</span>
                </span>
              </label>
              <input type="number" name="silver" id="silver" placeholder="Enter Weight in Grams" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="rental-income">Rental Income</label>
              <input type="number" name="rental-income" id="rental-income" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="property-value">Property Value</label>
              <input type="number" name="property-value" id="property-value" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="assets-other">Other</label>
              <input type="number" name="assets-other" id="assets-other" placeholder="$0" class="zkt-calc__input">
            </div>
          </div>
        </div>
        
        <!-- Liabilities Section -->
        <div class="zkt-calc__section">
          <div class="zkt-calc__section-header">
            <h2 class="zkt-calc__section-title">Liabilities</h2>
            <h5 class="zkt-calc__section-subtitle">
              Liabilities you own on which Zakat is payable
              <span class="zkt-calc__tooltip">
                <span class="zkt-calc__tooltip-icon">i</span>
                <span class="zkt-calc__tooltip-text">These are debts and obligations that can be deducted from your zakatable assets.</span>
              </span>
            </h5>
          </div>
          
          <div class="zkt-calc__fields-grid">
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="credit-card-payments">Credit Card Payments</label>
              <input type="number" name="credit-card-payments" id="credit-card-payments" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="home-payments">Home Payments</label>
              <input type="number" name="home-payments" id="home-payments" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="car-payments">Car Payments</label>
              <input type="number" name="car-payments" id="car-payments" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="business-payments">Business Payments</label>
              <input type="number" name="business-payments" id="business-payments" placeholder="$0" class="zkt-calc__input">
            </div>
            
            <div class="zkt-calc__field">
              <label class="zkt-calc__label" for="liabilities-other">Other</label>
              <input type="number" name="liabilities-other" id="liabilities-other" placeholder="$0" class="zkt-calc__input">
            </div>
          </div>
        </div>
      </div>
      
      <?php
      $goldWeight = 87.48;
      $silverWeight = 612.36;

      $goldNisab = $goldWeight * $goldPricePerGram;
      $silverNisab = $silverWeight * $silverPricePerGram;
      ?>

      <!-- Results Section -->
      <div class="zkt-calc__results" id="zakat-due-sticky-container">
        <div class="zkt-calc__results-inner">
          <div>
            <h2 class="zkt-calc__results-title">Estimated Zakat Due</h2>
            <p class="zkt-calc__due-amount">$0.00</p>
            
            <div class="zkt-calc__row">
              <span>Total Assets</span>
              <span class="zkt-calc__row-value total-assets">$0.00</span>
            </div>
            
            <div class="zkt-calc__row">
              <span>Total Liabilities</span>
              <span class="zkt-calc__row-value total-liabilities">$0.00</span>
            </div>
            
            <div class="zkt-calc__row">
              <span>Zakatable Assets</span>
              <span class="zkt-calc__row-value zakatable-assets">$0.00</span>
            </div>
          </div>
          <div>
            <!-- <h3 class="zkt-calc__nisab-title">Nisab</h3> -->
            <p class="zkt-calc__nisab-text">
              Zakat is not obligatory if your net zakatable assets are below the zakatable minimum (nisab) of current 
              <?= $goldWeight ?>g gold price (<span class="zkt-calc__price-value">$<?= number_format($goldNisab, 2) ?></span>) or 
              <?= $silverWeight ?>g silver price (<span class="zkt-calc__price-value">$<?= number_format($silverNisab, 2) ?></span>).
            </p>
          </div>

          <div>
            <!-- <h3 class="zkt-calc__nisab-title">Nisab</h3> -->
            <p class="zkt-calc__donate-text">
             <strong> Have you completed calculating your zakat? </strong>
              Give your zakat today to deserving Islamic 
              scholars and students in need.
            </p>
          </div>


          <a href="<?php echo esc_url($donation_url); ?>" class="zkt-calc__donate-btn">Give Zakat Now</a>
        </div>
      </div>
  
  <script>
    function formatNumber(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function convertOunceToGram(ounce) {
      return ounce * 31.1035;
    }

    document.addEventListener("DOMContentLoaded", function() {
      const calculateZakat = () => {
        // Get values from input fields
        let cashAtHome = parseFloat(document.getElementById("cash-at-home").value) || 0;
        let cashInBank = parseFloat(document.getElementById("cash-in-bank").value) || 0;
        let cashInBusiness = parseFloat(document.getElementById("cash-in-business").value) || 0;
        let rentalIncome = parseFloat(document.getElementById("rental-income").value) || 0;
        let propertyValue = parseFloat(document.getElementById("property-value").value) || 0;
        let gold = ((parseFloat(document.getElementById("gold").value) || 0) * 146.5);
        let silver = ((parseFloat(document.getElementById("silver").value) || 0) * 1.6);
        let assetsOther = parseFloat(document.getElementById("assets-other").value) || 0;

        let creditCardPayments = parseFloat(document.getElementById("credit-card-payments").value) || 0;
        let homePayments = parseFloat(document.getElementById("home-payments").value) || 0;
        let carPayments = parseFloat(document.getElementById("car-payments").value) || 0;
        let businessPayments = parseFloat(document.getElementById("business-payments").value) || 0;
        let liabilitiesOther = parseFloat(document.getElementById("liabilities-other").value) || 0;

        // Calculate total Zakatable wealth
        let goldPerGram = ((parseFloat(document.getElementById("gold").value) || 0) * <?= $goldPricePerGram ?>);
        let silverPerGram = ((parseFloat(document.getElementById("silver").value) || 0) * <?= $silverPricePerGram ?>);

        let totalWealth = cashAtHome + cashInBank + cashInBusiness + rentalIncome + propertyValue + assetsOther + goldPerGram + silverPerGram;
        let totalLiabilities = creditCardPayments + homePayments + carPayments + businessPayments + liabilitiesOther;
        let totalZakatableWealth = 0;
        if(totalWealth > 0){
          totalZakatableWealth = totalWealth - totalLiabilities;
          if(totalZakatableWealth < 0){
            totalZakatableWealth = 0;
          }
        }
        // Apply Zakat formula (2.5%)
        let zakatDue = totalZakatableWealth * 0.025;
        if (zakatDue < 0){
          zakatDue = 0;
        }
        // Update the zakat due amount in the .zakat-due-amount element
        document.querySelector(".zkt-calc__due-amount").textContent = `$${formatNumber(zakatDue.toFixed(2))}`;
        document.querySelector(".total-assets").textContent = `$${formatNumber(totalWealth.toFixed(2))}`;
        document.querySelector(".total-liabilities").textContent = `$${formatNumber(totalLiabilities.toFixed(2))}`;
        document.querySelector(".zakatable-assets").textContent = `$${formatNumber(totalZakatableWealth.toFixed(2))}`;
      };

      // Add event listener to calculate when input values change
      document.querySelectorAll(".zkt-calc__input").forEach(input => {
        input.addEventListener("input", calculateZakat);
      });
    });

    let scrollTimeout;
    window.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        let firstStickyHeight = 0;

        document.querySelectorAll('*').forEach(el => {
          if (getComputedStyle(el).position === 'sticky' || getComputedStyle(el).position === 'fixed') {
            if (el.id !== 'zakat-due-sticky-container' && firstStickyHeight === 0) {
              firstStickyHeight = el.offsetHeight;
            }
          }
        });

        const zakatSticky = document.getElementById('zakat-due-sticky-container');
        if (zakatSticky && firstStickyHeight > 0) {
          zakatSticky.style.top = `${firstStickyHeight + 20}px`;
        }
      }, 100); // Adjust delay if needed
    });
  </script>
<?php
  return ob_get_clean();
}
add_shortcode('zakat_calculator', 'zakat_calculator_shortcode');