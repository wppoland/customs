# DRAFT, NOT SENT

To: somnath.paramshetti@ssrf.org
Ref: WP-20260829-5ADC08
Subject: Re: Customs, subcategories and HS codes

---

Hello Somnath,

You are right, and thank you for testing it so quickly. My change fixed your books by breaking your Other Products, and your catalogue is exactly the case that shows why.

**What I got wrong.** I made every subcategory count under its parent. That is the right answer for Books > Health and Books > Self improvement, which really are one tariff line. It is the wrong answer for Other Products > Beads and Other Products > Pictures, which really are two. Both are a parent with children, and the category tree carries nothing that tells the two apart. So the rule cannot be a rule.

Version **1.0.12** is on the plugin directory now. Grouping is a setting, off by default, so your Other Products go back to counting as two lines straight away. The books case keeps working through the tariff codes, which is where it should have been from the start.

That also explains why unchecking the parent category did not help you. The plugin was reading the category tree, not the ticked boxes, so Beads still sat under Other Products as far as it was concerned.

**On your main question: yes, the HS code should already win.** A tariff code set on a product is checked before any category rule, in either mode. I reproduced your scenario on a clean install: two products, one shared parent category, two different codes, and the plugin counts two lines. So the logic you are asking for is the logic that is there, which means on your shop the codes are not reaching the plugin.

There are two easy ways for that to happen, and 1.0.12 will tell you which:

The settings screen now reports how many of your published products carry a tariff code the plugin can actually read. Please update, open WooCommerce and then EU Import Duty, and look at that line. If it says a number far below your product count, the codes are not where the plugin looks.

The two usual reasons:

1. The field is called **Customs tariff code** and sits on the product's **Shipping** tab. WooCommerce hides that tab entirely for products marked **Virtual**, so those products cannot have the field at all.

2. If you entered the HS codes in a field provided by WooCommerce Shipping or by another plugin, this plugin does not read them. It only reads its own field.

If the number looks wrong, tell me what it says and whether your products are simple or variable, and I will take it from there. If you would rather not re-enter codes by hand, tell me which field they are in now and I will add a small filter so the plugin reads them where they already are.

Sorry for the round trip on this one.

Best regards,
Mariusz Szatkowski
WPPoland
