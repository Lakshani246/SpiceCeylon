<?php
session_start();
include "header.php";

// Ensure user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}
?>

<link rel="stylesheet" href="../assets/css/home.css">
<script src="../assets/js/home.js" defer></script>

<div class="home-container">
    <!-- Video Banner -->
    <div class="video-banner">
        <video autoplay muted loop class="banner-video">
            <source src="../assets/videos/landing-video.mp4" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
        <div class="video-overlay">
            <h1>Welcome to SpiceCeylon</h1>
            <p>Discover the finest Sri Lankan spices at your fingertips.</p>
        </div>
    </div>

    <!-- Spice Categories -->
    <?php
    $categories = [
        "Core Sri Lankan Spices" => [
            ["id"=>1,"name"=>"Cinnamon","sinhala"=>"Kurundu - කුරුඳු","desc"=>"True Ceylon Cinnamon is a national treasure, sweeter and more complex than Cassia."],
            ["id"=>2,"name"=>"Cardamom","sinhala"=>"Enasal - එනසාල්","desc"=>"Both green and black cardamom are used, with green being more common for its intense aroma."],
            ["id"=>3,"name"=>"Cloves","sinhala"=>"Karabu Neti - කරාබු නැටි","desc"=>"Used whole in meat dishes, rice pilafs, and spice mixtures."],
            ["id"=>4,"name"=>"Nutmeg & Mace","sinhala"=>"Sadikka - සාදික්කා / Wasadisi - වාසදිසි","desc"=>"Nutmeg is the seed, mace is the lacy red covering. Both have a warm, sweet flavor."],
            ["id"=>5,"name"=>"Black Pepper","sinhala"=>"Gammiris - ගම්මිරිස්","desc"=>"A key export and staple for heat and flavor."],
            ["id"=>6,"name"=>"Cumin Seeds","sinhala"=>"Suduru - සුදුරු","desc"=>"Essential for curry powders, providing an earthy base note."],
            ["id"=>7,"name"=>"Coriander Seeds","sinhala"=>"Kottamalli - කොත්තමල්ලි","desc"=>"The most common base for Sri Lankan curry powders."],
            ["id"=>8,"name"=>"Fennel Seeds","sinhala"=>"Maduru - මාදුරු","desc"=>"Has a sweet, licorice-like flavor, used in specific meat and fish curries."],
            ["id"=>9,"name"=>"Fenugreek Seeds","sinhala"=>"Uluhal - උලුහාල්","desc"=>"Used sparingly for its bitter, maple-syrup-like flavor. A key ingredient in roasted curry powder."],
            ["id"=>10,"name"=>"Mustard Seeds","sinhala"=>"Aba - අබ","desc"=>"Used for tempering (tarka) at the start of cooking to release their nutty flavor."],
            ["id"=>11,"name"=>"Turmeric","sinhala"=>"Kaha - කහ","desc"=>"Provides vibrant yellow color and an earthy, slightly bitter flavor. Used fresh as a root or in powder form."],
        ],

       "Fresh Herbs & Aromatics" => [
            ["id"=>12,"name"=>"Pandan Leaves","sinhala"=>"Rampe - ‌රම්පේ","desc"=>"Long, blade-like leaves used to infuse a nutty, vanilla-like aroma into rice, curries, and sweets."],
            ["id"=>13,"name"=>"Curry Leaves","sinhala"=>"Karapincha - ‌කරපිංචා","desc"=>"Used for tempering and simmering in almost every savory dish."],
            ["id"=>14,"name"=>"Lemongrass","sinhala"=>"Sera - සේර","desc"=>"Used in stalks, bruised and added to curries and teas for a strong citrus scent."],
            ["id"=>15,"name"=>"Sri Lankan Ginger","sinhala"=>"Inguru - ඉඟුරු","desc"=>"Fresh and pungent, core ingredient in curry base paste."],
            ["id"=>16,"name"=>"Garlic","sinhala"=>"Sudulunu - සුදුලුනු","desc"=>"Used abundantly in pastes and tempering."],
            ["id"=>17,"name"=>"Ceylon Citron / Lemon","sinhala"=>"Dehi - දෙහි / Pangiri - පැංගිරි","desc"=>"The unique, thick-skinned citron is used for its rind and juice."],
        ],

        "Chilies & Heat Elements" => [
            ["id"=>18,"name"=>"Ceylon Chili / Bird's Eye Chili","sinhala"=>"Kochchi - කොච්චි","desc"=>"Extremely spicy, small chilies used whole, sliced, or as a powder."],
            ["id"=>19,"name"=>"Chili Powder","sinhala"=>"Kochchi Thool - කොච්චි තුළ්","desc"=>"Made from dried and ground red chilies, different heat levels available."],
            ["id"=>20,"name"=>"Black Mustard Seeds","sinhala"=>"","desc"=>"Can also provide a pungent heat when ground into a paste."],
        ],

        "Specialty & Regional Spices" => [
            ["id"=>21,"name"=>"Goraka","sinhala"=>"Goraka - ගොරකා","desc"=>"Dried fruit pods used as a souring agent, especially in fish curries and Mallung."],
            ["id"=>22,"name"=>"Tamarind","sinhala"=>"Siyambala - සියඹලා","desc"=>"The pulp adds a sharp, fruity sourness to dishes."],
            ["id"=>23,"name"=>"Screw Pine / Kewra","sinhala"=>"Wathakesi - වතකෙසි","desc"=>"Used in sweets and drinks, similar to Pandan."],
            ["id"=>24,"name"=>"Licorice Powder","sinhala"=>"-","desc"=>"Used in Ayurvedic preparations and meat marinades."],
            ["id"=>25,"name"=>"Annatto Seeds","sinhala"=>"Kurkuman - කුර්කුමන්","desc"=>"Used for their vibrant red-orange color."],
            ["id"=>26,"name"=>"Ajwain (Carom Seeds)","sinhala"=>"Asamodagam - අසමෝදගම්","desc"=>"Strong thyme-like flavor, used in digestifs and some bread."],
            ["id"=>27,"name"=>"Dill","sinhala"=>"Endaru - එන්‌ඩරු","desc"=>"Used in sambols and salads."],
            ["id"=>28,"name"=>"Sweet Flag","sinhala"=>"Wadakaha - වදකහ","desc"=>"An Ayurvedic herb used medicinally."],
        ],

        "Spice Blends" => [
            ["id"=>29,"name"=>"Roasted Curry Powder","sinhala"=>"Badum Thel Kudu - බැදුම් තෙල් කුඩු","desc"=>"Dark, intensely fragrant powder used for meat and vegetable curries."],
            ["id"=>30,"name"=>"Unroasted Curry Powder","sinhala"=>"-","desc"=>"Lighter blend used for fish and dhal curries."],
            ["id"=>31,"name"=>"Chili Paste","sinhala"=>"Kochchi Miris Hodi - කොච්චි මිරිස් ‌හොදි","desc"=>"Ground paste used as base for curries."],
        ]
    ];

    foreach ($categories as $category => $items) {
        echo "<h2 class='section-title'>$category</h2>";
        echo "<div class='spices-grid'>";

        foreach ($items as $spice) {
            $imageName = strtolower(str_replace(' ', '-', $spice['name']));
            $imagePath = "../assets/images/".$imageName.".jpg";

            echo '<div class="spice-card">';
            echo '<img src="'.$imagePath.'" alt="'.$spice['name'].'" onerror="console.log(\'Image failed to load: '.$imagePath.'\')">';
            echo '<h3>'.$spice['name'].'</h3>';
            echo '<p><strong>'.$spice['sinhala'].'</strong></p>';
            echo '<p>'.$spice['desc'].'</p>';

            // Only View button
            echo '<div class="card-controls">';
            echo '<a href="view_product.php?product_id='.$spice['id'].'" class="btn-view">View</a>';
            echo '</div>';

            echo '</div>'; // spice-card
        }

        echo "</div>";
    }
    ?>
</div>

<?php include "footer.php"; ?>
