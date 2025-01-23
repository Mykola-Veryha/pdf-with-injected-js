# Using qPDF for JavaScript Detection in PDFs

## Why QPDF?

### Performance
- Processes large PDFs (30MB+) significantly faster than alternatives
- Benchmark on 30 MB file:
  ```
  qpdf:   0.76s user 0.07s system 99% cpu 0.827 total
  peepdf: 5.84s user 0.37s system 99% cpu 6.244 total
  ```

### Smart Warning Handling
- Continues processing despite PDF structure warnings
- Distinguishes between structural issues and security threats
- Example: Will process a PDF with missing XRef table while still detecting malicious JS

### Advanced Content Decoding
- Automatically unpacks hex-encoded content
- Decodes compressed streams
- Exposes obfuscated JavaScript
- Example transformation:
  ```
  From: /JS <6170702E616C657274282248656C6C6F20776F726C642122293B0A>
  To:   /JS (app.alert("Hello world!");)
  ```

## JavaScript Detection Points
PDF files can contain JavaScript in several locations. Here are the areas to check:

1. Direct JavaScript (/JS)
```
/S /JavaScript
/JS (app.alert("Hello"); console.log("test"))
```

2. JavaScript Actions (/Type /Action)
```
/Type /Action
/S /JavaScript
/JS <6170702E616C657274282248656C6C6F20776F726C642122293B0A>
```

3. URI Actions with JavaScript
```
/A<</S/URI/URI(javascript:app.alert('Malicious JavaScript');)>>
```

4. Text Annotations with JavaScript
```
/Type /Annot
/Subtype /Text
/Contents (">'>'<details open ontoggle=confirm('XSS')")
```

5. OpenAction JavaScript (runs on document open)
```
/OpenAction <<
/JS (app.alert(1);)
/S /JavaScript
/Type /Action
>>
```

An important note: Not all PDF readers will execute JavaScript from these locations. Different readers have varying levels of JavaScript support. However, we check all these locations to ensure comprehensive security

