# WioEX SDK Credit Calculator Guide

## 💰 **Kredi Tüketimi Hesaplama Rehberi**

Bu rehber WioEX SDK bulk operations'ın **gerçek kredi tüketimini** hesaplamanıza yardımcı olur.

## 🧮 **Temel Formüller**

### **quoteBulk() - Gerçek Tasarruf:**
```
Kredi = ⌈ Hisse Sayısı ÷ 30 ⌉
Tasarruf = ((Hisse Sayısı - Kredi) ÷ Hisse Sayısı) × 100%
```

### **timelineBulk/infoBulk/financialsBulk - Tasarruf Yok:**
```
Kredi = Hisse Sayısı × 1
Tasarruf = 0%
```

## 📊 **Hızlı Hesap Tablosu**

### **quoteBulk() Kredi Tablosu:**

| Hisse Sayısı | Chunk Sayısı | Kredi Tüketimi | Individual vs Bulk | Tasarruf |
|---------------|---------------|----------------|-------------------|----------|
| 1-30 | 1 | **1 kredi** | 1-30 vs 1 | 0%-97% |
| 31-60 | 2 | **2 kredi** | 31-60 vs 2 | 67%-97% |
| 61-90 | 3 | **3 kredi** | 61-90 vs 3 | 67%-97% |
| 91-120 | 4 | **4 kredi** | 91-120 vs 4 | 73%-97% |
| 121-150 | 5 | **5 kredi** | 121-150 vs 5 | 75%-97% |
| 200 | 7 | **7 kredi** | 200 vs 7 | **97% tasarruf** |
| 300 | 10 | **10 kredi** | 300 vs 10 | **97% tasarruf** |
| 500 | 17 | **17 kredi** | 500 vs 17 | **97% tasarruf** |
| 1000 | 34 | **34 kredi** | 1000 vs 34 | **97% tasarruf** |

### **Diğer Bulk Operations (Tasarruf Yok):**

| Hisse Sayısı | timelineBulk | infoBulk | financialsBulk |
|---------------|--------------|----------|----------------|
| 10 | **10 kredi** | **10 kredi** | **10 kredi** |
| 50 | **50 kredi** | **50 kredi** | **50 kredi** |
| 100 | **100 kredi** | **100 kredi** | **100 kredi** |
| 500 | **500 kredi** | **500 kredi** | **500 kredi** |

## 🧮 **Interactive Calculator**

### **JavaScript Calculator (Embed edilebilir):**

```html
<!DOCTYPE html>
<html>
<head>
    <title>WioEX Credit Calculator</title>
    <style>
        .calculator { max-width: 600px; margin: 20px; font-family: Arial; }
        .input-group { margin: 10px 0; }
        .result { background: #f0f8ff; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .savings { color: #28a745; font-weight: bold; }
        .no-savings { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="calculator">
        <h2>🧮 WioEX SDK Credit Calculator</h2>
        
        <div class="input-group">
            <label>Hisse Senedi Sayısı:</label>
            <input type="number" id="stockCount" value="500" min="1" max="1000">
        </div>
        
        <div class="input-group">
            <label>Operations (Multiple selection possible):</label><br>
            <input type="checkbox" id="quotes" checked> quoteBulk() (Real savings)<br>
            <input type="checkbox" id="timeline"> timelineBulk() (No savings)<br>
            <input type="checkbox" id="info"> infoBulk() (No savings)<br>
            <input type="checkbox" id="financials"> financialsBulk() (No savings)<br>
        </div>
        
        <button onclick="calculate()">Calculate Credits</button>
        
        <div id="results"></div>
    </div>

    <script>
        function calculate() {
            const stockCount = parseInt(document.getElementById('stockCount').value);
            const quotes = document.getElementById('quotes').checked;
            const timeline = document.getElementById('timeline').checked;
            const info = document.getElementById('info').checked;
            const financials = document.getElementById('financials').checked;
            
            let totalCredits = 0;
            let totalIndividual = 0;
            let results = '';
            
            if (quotes) {
                const quotesCredits = Math.ceil(stockCount / 30);
                const quotesSavings = Math.round((1 - quotesCredits / stockCount) * 100);
                totalCredits += quotesCredits;
                totalIndividual += stockCount;
                results += `<div class="result">
                    <strong>📈 quoteBulk():</strong> ${quotesCredits} credits 
                    <span class="savings">(${quotesSavings}% savings vs ${stockCount} individual)</span>
                </div>`;
            }
            
            if (timeline) {
                totalCredits += stockCount;
                totalIndividual += stockCount;
                results += `<div class="result">
                    <strong>📊 timelineBulk():</strong> ${stockCount} credits 
                    <span class="no-savings">(0% savings - same as individual)</span>
                </div>`;
            }
            
            if (info) {
                totalCredits += stockCount;
                totalIndividual += stockCount;
                results += `<div class="result">
                    <strong>ℹ️ infoBulk():</strong> ${stockCount} credits 
                    <span class="no-savings">(0% savings - same as individual)</span>
                </div>`;
            }
            
            if (financials) {
                totalCredits += stockCount;
                totalIndividual += stockCount;
                results += `<div class="result">
                    <strong>💼 financialsBulk():</strong> ${stockCount} credits 
                    <span class="no-savings">(0% savings - same as individual)</span>
                </div>`;
            }
            
            if (totalCredits > 0) {
                const overallSavings = Math.round((1 - totalCredits / totalIndividual) * 100);
                results += `<div class="result" style="border: 2px solid #007bff;">
                    <strong>💰 TOTAL:</strong> ${totalCredits} credits vs ${totalIndividual} individual<br>
                    <strong>📈 Overall Savings:</strong> ${overallSavings}%
                </div>`;
            }
            
            document.getElementById('results').innerHTML = results || '<p>Please select at least one operation.</p>';
        }
        
        // Auto-calculate on page load
        calculate();
    </script>
</body>
</html>
```

## 📈 **Gerçek Dünya Örnekleri**

### **Örnek 1: Portfolio Monitoring (Sadece Quotes)**
```php
$portfolio = 500; // stocks
$quotes = $client->stocks()->quoteBulk($portfolioSymbols);
```
**Kredi:** 17 (vs 500 individual) = **%97 tasarruf** ✅

### **Örnek 2: Comprehensive Analysis (Mixed)**
```php
$portfolio = 100; // stocks
$quotes = $client->stocks()->quoteBulk($symbols);        // 4 kredi
$timelines = $client->stocks()->timelineBulk($symbols);  // 100 kredi
$info = $client->stocks()->infoBulk($symbols);          // 100 kredi
// Total: 204 kredi (vs 300 individual) = %32 tasarruf
```

### **Örnek 3: Research Heavy (Sadece Data)**
```php
$watchlist = 50; // stocks
$timelines = $client->stocks()->timelineBulk($symbols);  // 50 kredi
$info = $client->stocks()->infoBulk($symbols);          // 50 kredi  
$financials = $client->stocks()->financialsBulk($symbols); // 50 kredi
// Total: 150 kredi (vs 150 individual) = %0 tasarruf
```

## 💡 **Optimizasyon Stratejileri**

### **1. Quotes-First Strategy:**
```php
// ✅ Önce quotes (tasarruf için)
$quotes = $client->stocks()->quoteBulk($allSymbols); // 17 kredi for 500

// ⚠️ Sonra sadece gerekli olanlar için detail
$importantSymbols = filterImportantStocks($quotes);
$details = $client->stocks()->infoBulk($importantSymbols); // Reduced cost
```

### **2. Tiered Analysis:**
```php
// Tier 1: Bulk quotes for screening
$quotes = $client->stocks()->quoteBulk($largeUniverse); // Low cost

// Tier 2: Individual details for candidates  
foreach ($topCandidates as $symbol) {
    $detail = $client->stocks()->timeline($symbol); // Targeted cost
}
```

### **3. Smart Caching:**
```php
// Cache non-real-time data
$cached = getCachedInfo($symbols);
$newSymbols = array_diff($symbols, array_keys($cached));
$fresh = $client->stocks()->infoBulk($newSymbols); // Reduced API calls
```

## 🎯 **Use Case Matrix**

| Use Case | Best Strategy | Expected Credits (500 stocks) | Savings |
|----------|---------------|-------------------------------|---------|
| **Real-time Trading** | quoteBulk() only | **17 credits** | 97% |
| **Portfolio Monitoring** | quoteBulk() + selective details | **~50 credits** | 90% |
| **Research Platform** | Mixed operations | **~517 credits** | 3% |
| **Data Archive** | Individual calls | **~1500 credits** | 0% |

## 🚨 **Yaygın Hatalar**

### **❌ Yanlış Beklenti:**
```php
// "All bulk operations save credits"
$everything = getAllBulkData($500_symbols); // Expecting 50 credits
// Reality: ~1017 credits (quotes: 17 + others: 1000)
```

### **✅ Doğru Kullanım:**
```php
// "Only quotes save credits"
$quotes = $client->stocks()->quoteBulk($symbols); // 17 credits ✅
$details = getSelectiveDetails($importantOnes);   // Calculated cost ✅
```

## 📞 **Destek ve Araçlar**

### **Credit Monitoring:**
```php
// Built-in credit tracking (if available)
$credits = $client->account()->getCredits();
echo "Remaining: {$credits['remaining']} credits\n";
```

### **Estimation Tool:**
```php
// Custom estimation function
function estimateCredits($operations) {
    $total = 0;
    foreach ($operations as $op => $count) {
        if ($op === 'quotes') {
            $total += ceil($count / 30);
        } else {
            $total += $count;
        }
    }
    return $total;
}

$estimate = estimateCredits([
    'quotes' => 500,     // 17 credits
    'timelines' => 100   // 100 credits  
]); // Total: 117 credits
```

## 🔄 **Güncelleme Bildirimi**

Bu hesaplamalar WioEX API v2.1.0 limitlerine göre yapılmıştır:
- **Quotes**: 30 symbol per request
- **Timeline/Info/Financials**: 1 symbol per request

API limitlerinde değişiklik olması durumunda bu doküman güncellenecektir.

---

**Son Güncelleme:** 2025-10-22  
**API Version:** v2.1.0  
**SDK Version:** v2.1.0