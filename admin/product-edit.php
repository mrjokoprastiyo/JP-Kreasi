<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

$id = (int)($_GET['id'] ?? 0);
if(!$id) die('ID tidak valid');

/* ===============================
   LOAD PRODUCT GROUP
================================ */

$product = DB::fetch("
SELECT * FROM products WHERE id = ?
",[$id]);

if(!$product) die('Product tidak ditemukan');

/* Ambil semua varian produk */
$variants = DB::fetchAll("
SELECT * FROM products
WHERE name = ?
AND category = ?
AND sub_category = ?
ORDER BY duration ASC
",[
$product['name'],
$product['category'],
$product['sub_category']
]);

/* ===============================
   CATEGORY LIST
================================ */

$categories = DB::fetchAll("
SELECT DISTINCT category FROM products
WHERE category IS NOT NULL AND category!=''
ORDER BY category ASC
");

$subCategories = DB::fetchAll("
SELECT DISTINCT sub_category FROM products
WHERE sub_category IS NOT NULL AND sub_category!=''
ORDER BY sub_category ASC
");

include "header.php";
include "sidebar.php";
?>

<main class="user-content">

<div class="page-header">
<h2>✏️ Edit Product</h2>
<a href="product-list.php" class="btn-back">← Back to List</a>
</div>

<form method="post" action="product-edit-save.php">
<input type="hidden" name="product_id" value="<?= $product['id'] ?>">

<div class="settings-grid">

<section class="card">

<h3>📦 Product Information</h3>

<div class="form-group">
<label>Product Name</label>
<input type="text" name="name" required
value="<?= htmlspecialchars($product['name']) ?>">
</div>

<div class="form-group">
<label>Description</label>
<textarea name="description" rows="5"><?= htmlspecialchars($product['description']) ?></textarea>
</div>

<div class="settings-grid grid-2">

<div class="form-group">
<label>Category</label>

<select name="category_select" id="categorySelect">

<option value="">-- Select Category --</option>

<?php foreach($categories as $c): ?>

<option value="<?= htmlspecialchars($c['category']) ?>"
<?= $c['category']==$product['category']?'selected':'' ?>>

<?= htmlspecialchars($c['category']) ?>

</option>

<?php endforeach; ?>

<option value="__new__">+ New Category</option>

</select>

<input
type="text"
name="category_new"
id="categoryNew"
class="hidden-input">

</div>


<div class="form-group">

<label>Sub Category</label>

<select name="sub_category_select" id="subCategorySelect">

<option value="">-- Select Sub Category --</option>

<?php foreach($subCategories as $s): ?>

<option value="<?= htmlspecialchars($s['sub_category']) ?>"
<?= $s['sub_category']==$product['sub_category']?'selected':'' ?>>

<?= htmlspecialchars($s['sub_category']) ?>

</option>

<?php endforeach; ?>

<option value="__new__">+ New Sub Category</option>

</select>

<input
type="text"
name="sub_category_new"
id="subCategoryNew"
class="hidden-input">

</div>

</div>

</section>


<section class="card">

<h3>⚙️ Service Configuration</h3>

<div class="form-group config-toggle" id="serviceGroup">

<label>Service</label>

<select name="service" id="serviceField">

<option value="">-- Select Service --</option>

<?php
$services=['website','whatsapp','telegram','email','messenger'];
foreach($services as $s){
$sel=$product['service']==$s?'selected':'';
echo "<option value='$s' $sel>$s</option>";
}
?>

</select>

</div>


<div class="form-group config-toggle" id="typeGroup">

<label>Product Type</label>

<select name="product_type" id="typeField">

<option value="">-- Select Type --</option>

<option value="system"
<?= $product['product_type']=='system'?'selected':'' ?>>System</option>

<option value="client"
<?= $product['product_type']=='client'?'selected':'' ?>>Client</option>

</select>

</div>


<div class="form-group config-toggle" id="tierGroup">

<label>Tier</label>

<input
type="number"
name="tier"
id="tierField"
value="<?= $product['tier'] ?>"
min="1">

</div>

</section>


<section class="card">

<h3>💰 Product Variations</h3>

<div id="variantContainer">

<?php foreach($variants as $v): ?>

<div class="variant-row">

<div class="duration-col">

<input
type="number"
name="duration[]"
class="duration-input"
value="<?= $v['duration'] ?>"
placeholder="Days">

<small class="duration-label"></small>

</div>

<input
type="number"
name="price_idr[]"
value="<?= $v['price_idr'] ?>"
placeholder="Price IDR"
required>

<input
type="number"
name="price_usd[]"
value="<?= $v['price_usd'] ?>"
placeholder="Price USD">

<button type="button"
class="btn-remove"
onclick="removeVariant(this)">❌</button>

</div>

<?php endforeach; ?>

</div>

<button type="button" class="btn-add" onclick="addVariant()">
+ Add Variation
</button>


<div class="form-group status-box">

<label>Status</label>

<select name="status">

<option value="active"
<?= $product['status']=='active'?'selected':'' ?>>Active</option>

<option value="inactive"
<?= $product['status']=='inactive'?'selected':'' ?>>Inactive</option>

</select>

</div>

</section>

</div>

<button type="submit" class="btn-save">
💾 Update Product
</button>

</form>

</main>

<style>
    /* 1. VARIABLES & RESET */
    :root {
        --primary-color: #4f46e5;
        --primary-hover: #4338ca;
        --border-color: #e2e8f0;
        --bg-card: #ffffff;
        --bg-light: #f8fafc;
        --bg-disabled: #f1f5f9;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --danger: #ef4444;
        --success: #15803d;
        --warning: #92400e;
    }

    /* 2. LAYOUT & CONTAINERS */
    .settings-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--border-color);
    }

    .page-header h2 { 
        margin: 0; 
        font-size: 1.5rem; 
        color: var(--text-main); 
    }

    /* 3. GRID SYSTEM */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .grid-inner { display: grid; gap: 15px; }
    .grid-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-4 { grid-template-columns: repeat(4, 1fr); }

    .card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .card-full { grid-column: 1 / -1; }

    /* 4. FORM ELEMENTS */
    h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1rem;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid var(--bg-disabled);
        padding-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group { margin-bottom: 15px; }

    label {
        display: block;
        font-weight: 600;
        font-size: 0.85em;
        margin-bottom: 6px;
        color: var(--text-main);
    }

    input, select, textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9em;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    input:disabled, .input-disabled {
        background: var(--bg-disabled);
        color: var(--text-muted);
        cursor: not-allowed;
        font-weight: 600;
    }

    .hidden-input { display: none; margin-top: 8px; width: 100%; }

    /* 5. VARIATIONS & DURATION */
    .variant-row { 
        display: flex; 
        gap: 10px; 
        align-items: flex-start; 
        margin-bottom: 12px; 
        background: #f9f9f9;
        padding: 10px;
        border-radius: 6px;
    }
    
    .duration-col { flex: 1; }
    
    .variant-row input { 
        flex: 1; 
        padding: 8px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
    }
    
    .duration-label { 
        display: block; 
        color: var(--text-muted); 
        font-size: 11px; 
        margin-top: 4px; 
        height: 12px; 
    }

    .status-box { 
        border-top: 1px solid #eee; 
        padding-top: 15px; 
        margin-top: 20px;
    }

    /* 6. BUTTONS */
    .btn-back {
        text-decoration: none;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        background: var(--bg-light);
        border: 1px solid var(--border-color);
    }

    .btn-back:hover {
        background: #f1f5f9;
        color: var(--primary-color);
        border-color: var(--primary-color);
        transform: translateX(-3px);
    }

    .btn-create, .btn-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: var(--primary-color);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-save { width: 100%; font-size: 1em; padding: 14px; }
    
    .btn-create:hover, .btn-save:hover { 
        background: var(--primary-hover); 
        transform: translateY(-1px); 
    }
    
    .btn-remove { 
        background: #fee; 
        border: 1px solid #fcc; 
        cursor: pointer; 
        padding: 5px 10px; 
        border-radius: 4px; 
        color: var(--danger);
    }

    .btn-add { 
        background: #eef; 
        color: #44b; 
        border: 1px dashed #44b; 
        padding: 10px; 
        width: 100%; 
        cursor: pointer; 
        border-radius: 6px; 
    }

    .btn-reset { 
        background: #94a3b8; 
        color: white; 
        border: none; 
        padding: 12px; 
        border-radius: 8px; 
        cursor: pointer; 
        flex: 1; 
        font-weight: 600;
    }

    .btn-sm { 
        padding: 5px 10px; 
        background: var(--bg-disabled); 
        border: 1px solid var(--border-color); 
        border-radius: 6px; 
        cursor: pointer; 
        font-size: 0.8em; 
    }

    /* 7. COMPONENTS & UTILS */
    .status-badge {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75em;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-active { background: #dcfce7; color: var(--success); }
    .status-inactive, .status-suspended { background: #fee2e2; color: #b91c1c; }
    .status-pending { background: #fef3c7; color: var(--warning); }

    .stat-box {
        padding: 10px;
        background: var(--bg-disabled);
        border-radius: 8px;
        text-align: center;
        font-weight: bold;
        border: 1px solid var(--border-color);
    }

    .bot-avatar { 
        width: 40px; height: 40px; border-radius: 10px; 
        object-fit: cover; background: var(--bg-disabled); border: 1px solid var(--border-color);
    }

    .table-responsive { width: 100%; overflow-x: auto; }

    .action-bar { margin-top: 20px; display: flex; gap: 10px; }

    /* 8. RESPONSIVE BREAKPOINTS */
    @media (max-width: 900px) {
        .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
        .settings-grid, .grid-inner, .grid-2, .grid-3, .grid-4 { 
            grid-template-columns: 1fr; 
        }
        .action-bar { flex-direction: column; }
        .card-full { grid-column: auto; }
        .variant-row { flex-direction: column; }
        .variant-row input, .duration-col { width: 100%; }
    }
</style>

<script>

/* ===============================
JS SAMA DENGAN CREATE
=============================== */

const categorySelect=document.getElementById("categorySelect")
const subCategorySelect=document.getElementById("subCategorySelect")

const categoryNew=document.getElementById("categoryNew")
const subCategoryNew=document.getElementById("subCategoryNew")

const serviceGroup=document.getElementById("serviceGroup")
const typeGroup=document.getElementById("typeGroup")
const tierGroup=document.getElementById("tierGroup")

function getVal(selectEl,inputEl){
return (selectEl.value==="__new__"?inputEl.value:selectEl.value).toLowerCase().trim()
}

function updateUI(){

const cat=getVal(categorySelect,categoryNew)
const sub=getVal(subCategorySelect,subCategoryNew)

let showService=true
let showType=true
let showTier=true
let showDuration=true

if(cat==="design"||cat==="desain"){
showService=showType=showTier=showDuration=false
}
else if(cat==="automation"&&sub==="chatbot"){
showType=false
}

serviceGroup.style.display=showService?"block":"none"
typeGroup.style.display=showType?"block":"none"
tierGroup.style.display=showTier?"block":"none"

document.querySelectorAll(".duration-col").forEach(el=>{
el.style.display=showDuration?"block":"none"
})

}

function addVariant(){

const row=document.createElement("div")

row.className="variant-row"

row.innerHTML=`
<div class="duration-col">
<input type="number" name="duration[]" class="duration-input" placeholder="Days" min="1">
<small class="duration-label"></small>
</div>
<input type="number" name="price_idr[]" placeholder="Price IDR" required>
<input type="number" name="price_usd[]" placeholder="Price USD">
<button type="button" class="btn-remove" onclick="removeVariant(this)">❌</button>
`

document.getElementById("variantContainer").appendChild(row)

}

function removeVariant(btn){

const rows=document.querySelectorAll(".variant-row")

if(rows.length>1){
btn.closest(".variant-row").remove()
}

}

function formatDuration(days){

days=parseInt(days)

if(!days||days<=0) return ""

if(days>=360) return "≈ 1 Year"
if(days>=150) return "≈ 6 Months"
if(days>=28) return "≈ 1 Month"
if(days>=7) return "≈ 1 Week"

return days+" Days"

}

document.addEventListener("input",(e)=>{

if(e.target.classList.contains("duration-input")){

const col=e.target.closest(".duration-col")
const label=col.querySelector(".duration-label")

label.innerText=formatDuration(e.target.value)

}

})

updateUI()

</script>

<?php include "footer.php"; ?>