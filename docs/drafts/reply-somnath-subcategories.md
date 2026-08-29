# DRAFT, NOT SENT

To: somnath.paramshetti@ssrf.org
Ref: WP-20260829-5ADC08
Subject: Re: Customs, EU import duty counted per subcategory

---

Hello Somnath,

Thank you for the report, and for describing it precisely enough to reproduce. You found a real bug and it is fixed.

**What was wrong.** The duty counted one tariff line per category, but it used whichever category was assigned to the product and never looked at the category that sits above it. Books > Health and Books > Self improvement are two different categories, so two books were counted as two lines and charged twice. Worse, the result also depended on whether the parent category happened to be ticked as well, so two shops with the same structure could be charged differently.

**What changed.** In version 1.0.11, every category assigned to a product is resolved to its own top-level parent before the count. Your two books now count as one line. Products in genuinely different top-level categories, say Books and Incense, still count separately, which is the intended behaviour. The update is on the plugin directory now, so it will appear in your WordPress updates screen.

**On the tariff code.** That part should already work: a tariff code set on a product takes priority and is checked before categories, so products sharing a code count as one line whatever their categories are. Since it did not, the code is probably not reaching the plugin. Three things worth checking:

1. The field is called "Customs tariff code" and lives on the product's **Shipping** tab. WooCommerce hides that whole tab for products marked **Virtual**, so if any of your products are virtual or downloadable, the field is not visible and cannot be saved for them.

2. If you entered the HS codes somewhere else, for example a field provided by WooCommerce Shipping or by another plugin, the plugin will not see them. It reads only its own field.

3. For variable products the field is on the parent product, not on individual variations. Variations inherit the parent's code, which is intended, but a code set only on a variation will not be picked up.

If you update to 1.0.11 and the books still count as two lines, tell me and I will look further. It would help to know whether the products are simple or variable, whether any are virtual, and where exactly you entered the HS codes.

There is also now a filter, `customs/tariff_line_key`, if you ever want to group by a real HS heading held somewhere else. Happy to show you how if that is useful.

Thanks again for taking the time to write it up.

Best regards,
Mariusz Szatkowski
WPPoland
