# Sinh mẫu email vi-VN của AcmeVietnam: `python3 tools/generate-mail-templates.py`
#
# Khối dùng chung (đầu thư, bảng đơn hàng, chuyển khoản, tài liệu) viết một lần ở đây rồi
# chép vào từng mẫu — mẫu email của Shopware là chuỗi Twig độc lập, không include được.
# Sửa mẫu = sửa file này, chạy lại, commit cả kết quả. Mẫu đã nạp vào DB không tự cập nhật:
# cần migration mới (xem Migration…VietnameseMailTemplates).
import os
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "src", "Resources", "mail-templates", "vi-VN")

SHOP = "{% set shop = config('core.basicInformation.shopName') ?: salesChannel.translated.name %}"
ORDER_VARS = SHOP + """
{% set orderUrl = salesChannel.domains|first.url|trim('/', 'right') ~ '/account/order/' ~ order.deepLinkCode %}
{% set orderDate = order.orderDateTime|format_datetime(pattern='HH:mm, dd/MM/yyyy', locale='vi', timezone='Asia/Ho_Chi_Minh') %}
{% set currencyIsoCode = order.currency.isoCode %}"""
GREETING = "Chào {{ order.orderCustomer.lastName }} {{ order.orderCustomer.firstName }},"

WRAP_OPEN = '<div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.5; color:#1f1f1f;">'

ORDER_TABLE = """
    <table border="0" cellpadding="6" cellspacing="0" width="100%" style="font-family:Arial, Helvetica, sans-serif; font-size:13px; border-collapse:collapse;">
        <tr>
            <td bgcolor="#F5EFE6" style="border-bottom:1px solid #d9cfc1;"><strong>Sản phẩm</strong></td>
            <td bgcolor="#F5EFE6" style="border-bottom:1px solid #d9cfc1; text-align:center;"><strong>SL</strong></td>
            <td bgcolor="#F5EFE6" style="border-bottom:1px solid #d9cfc1; text-align:right;"><strong>Đơn giá</strong></td>
            <td bgcolor="#F5EFE6" style="border-bottom:1px solid #d9cfc1; text-align:right;"><strong>Thành tiền</strong></td>
        </tr>
        {% for lineItem in order.nestedLineItems %}
            {% set nestingLevel = 0 %}
            {% set nestedItem = lineItem %}
            {% block lineItem %}
                <tr>
                    <td style="border-bottom:1px solid #eee;{% if nestingLevel > 0 %} padding-left: {{ (nestingLevel + 1) * 10 }}px;{% endif %}">
                        {% if nestedItem.cover is defined and nestedItem.cover is not null %}<img src="{{ nestedItem.cover.url }}" alt="" width="56" height="auto" style="float:left; margin-right:8px;"/>{% endif %}
                        {{ nestedItem.label|u.wordwrap(80) }}
                        {% if nestedItem.payload.productNumber is defined %}<br><span style="color:#777;">Mã: {{ nestedItem.payload.productNumber }}</span>{% endif %}
                        {% if nestedItem.payload.options is defined and nestedItem.payload.options|length >= 1 %}
                            <br><span style="color:#777;">{% for option in nestedItem.payload.options %}{{ option.group }}: {{ option.option }}{% if not loop.last %} | {% endif %}{% endfor %}</span>
                        {% endif %}
                    </td>
                    <td style="border-bottom:1px solid #eee; text-align:center;">{{ nestedItem.quantity }}</td>
                    <td style="border-bottom:1px solid #eee; text-align:right; white-space:nowrap;">{{ nestedItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td style="border-bottom:1px solid #eee; text-align:right; white-space:nowrap;">{{ nestedItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
                {% if nestedItem.children.count > 0 %}
                    {% set nestingLevel = nestingLevel + 1 %}
                    {% for lineItem in nestedItem.children %}
                        {% set nestedItem = lineItem %}
                        {{ block('lineItem') }}
                    {% endfor %}
                {% endif %}
            {% endblock %}
        {% endfor %}
    </table>

    {% set delivery = order.deliveries.first %}
    <p>
        {% for shippingCost in order.deliveries %}
            Phí vận chuyển: {% if shippingCost.shippingCosts.totalPrice > 0 %}{{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}{% else %}Miễn phí{% endif %}<br>
        {% endfor %}
        <strong>Tổng cộng: {{ order.price.totalPrice|currency(currencyIsoCode) }}</strong><br>
        {% for calculatedTax in order.price.calculatedTaxes %}
            <span style="color:#777;">{% if order.taxStatus is same as('net') %}Chưa gồm{% else %}Đã gồm{% endif %} VAT {{ calculatedTax.taxRate }}%: {{ calculatedTax.tax|currency(currencyIsoCode) }}</span><br>
        {% endfor %}
    </p>

    <p>
        {% if order.transactions is defined and order.transactions is not empty %}
            <strong>Thanh toán:</strong> {{ order.transactions.last.paymentMethod.translated.name }}<br>
        {% endif %}
        {% if delivery %}
            <strong>Vận chuyển:</strong> {{ delivery.shippingMethod.translated.name }}<br>
        {% endif %}
    </p>

    {% set address = delivery ? delivery.shippingOrderAddress : order.addresses.get(order.billingAddressId) %}
    {% if address %}
        <p>
            <strong>Giao đến:</strong><br>
            {{ address.lastName }} {{ address.firstName }}{% if address.phoneNumber %} — {{ address.phoneNumber }}{% endif %}<br>
            {{ address.street }}<br>
            {{ address.city }}{% if address.countryState %}, {{ address.countryState.translated.name }}{% endif %}<br>
            {{ address.country.translated.name }}
        </p>
    {% endif %}"""

ORDER_TABLE_PLAIN = """{% for lineItem in order.lineItems %}
- {{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.productNumber is defined %} ({{ lineItem.payload.productNumber }}){% endif %} × {{ lineItem.quantity }} = {{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}
{% for shippingCost in order.deliveries %}Phí vận chuyển: {% if shippingCost.shippingCosts.totalPrice > 0 %}{{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}{% else %}Miễn phí{% endif %}
{% endfor %}Tổng cộng: {{ order.price.totalPrice|currency(currencyIsoCode) }}
{% for calculatedTax in order.price.calculatedTaxes %}{% if order.taxStatus is same as('net') %}Chưa gồm{% else %}Đã gồm{% endif %} VAT {{ calculatedTax.taxRate }}%: {{ calculatedTax.tax|currency(currencyIsoCode) }}
{% endfor %}
{% if order.transactions is defined and order.transactions is not empty %}Thanh toán: {{ order.transactions.last.paymentMethod.translated.name }}
{% endif %}{% set delivery = order.deliveries.first %}{% if delivery %}Vận chuyển: {{ delivery.shippingMethod.translated.name }}
{% endif %}{% set address = delivery ? delivery.shippingOrderAddress : order.addresses.get(order.billingAddressId) %}{% if address %}
Giao đến: {{ address.lastName }} {{ address.firstName }}{% if address.phoneNumber %} — {{ address.phoneNumber }}{% endif %}
{{ address.street }}, {{ address.city }}{% if address.countryState %}, {{ address.countryState.translated.name }}{% endif %}, {{ address.country.translated.name }}
{% endif %}"""

# Thông tin chuyển khoản do AcmePayment gắn vào email (biến `acmeBankTransfer`) — có thì hiện
BANK = """
    {% if acmeBankTransfer is defined and acmeBankTransfer.applicable %}
        <div style="border:1px solid #d9cfc1; padding:12px 16px; margin:16px 0;">
            <strong style="font-size:15px;">Thông tin chuyển khoản</strong><br>
            {% if acmeBankTransfer.instruction %}<span style="color:#555;">{{ acmeBankTransfer.instruction|nl2br }}</span><br>{% endif %}
            {% if acmeBankTransfer.configured %}
                <br>
                Ngân hàng: <strong>{{ acmeBankTransfer.bankName }}</strong><br>
                Chủ tài khoản: <strong>{{ acmeBankTransfer.accountHolder }}</strong><br>
                Số tài khoản: <strong>{{ acmeBankTransfer.accountNumber }}</strong><br>
                Số tiền: <strong>{{ acmeBankTransfer.amount|currency(currencyIsoCode) }}</strong><br>
                Nội dung chuyển khoản: <strong>{{ acmeBankTransfer.reference }}</strong>
            {% else %}
                Cửa hàng sẽ liên hệ với bạn để gửi thông tin tài khoản nhận tiền.
            {% endif %}
        </div>
    {% endif %}"""
BANK_PLAIN = """{% if acmeBankTransfer is defined and acmeBankTransfer.applicable %}
THÔNG TIN CHUYỂN KHOẢN
{% if acmeBankTransfer.instruction %}{{ acmeBankTransfer.instruction }}
{% endif %}{% if acmeBankTransfer.configured %}Ngân hàng: {{ acmeBankTransfer.bankName }}
Chủ tài khoản: {{ acmeBankTransfer.accountHolder }}
Số tài khoản: {{ acmeBankTransfer.accountNumber }}
Số tiền: {{ acmeBankTransfer.amount|currency(currencyIsoCode) }}
Nội dung chuyển khoản: {{ acmeBankTransfer.reference }}
{% else %}Cửa hàng sẽ liên hệ với bạn để gửi thông tin tài khoản nhận tiền.
{% endif %}
{% endif %}"""

DOCS = """
    {% if a11yDocuments is defined and a11yDocuments is not empty %}
        <p>
            Bản tài liệu dễ đọc (HTML) của đơn hàng:<br>
            {% for a11y in a11yDocuments %}
                {% set documentLink = (salesChannel.domains|first.url|trim('/', 'right') ~ '/account/order/document/' ~ a11y.documentId ~ '/' ~ a11y.deepLinkCode ~ '/' ~ a11y.fileExtension) %}
                — <a href="{{ documentLink }}" target="_blank">{{ documentLink }}</a><br>
            {% endfor %}
        </p>
    {% endif %}"""

FOOTER = """
    <p>
        Xem đơn hàng: <a href="{{ orderUrl }}">{{ orderUrl }}</a><br>
        Cần hỗ trợ, bạn chỉ cần trả lời email này.
    </p>
    <p>Trân trọng,<br>{{ shop }}</p>
</div>"""
FOOTER_PLAIN = """
Xem đơn hàng: {{ orderUrl }}
Cần hỗ trợ, bạn chỉ cần trả lời email này.

Trân trọng,
{{ shop }}"""

def order_mail(intro_html, intro_plain, with_table=False, with_bank=False):
    html = ORDER_VARS + "\n" + WRAP_OPEN + "\n    <p>" + GREETING + "</p>\n" + intro_html
    plain = ORDER_VARS + "\n" + GREETING + "\n\n" + intro_plain + "\n"
    if with_bank:
        html += BANK; plain += BANK_PLAIN
    if with_table:
        html += ORDER_TABLE; plain += "\n" + ORDER_TABLE_PLAIN
    html += DOCS + FOOTER
    plain += FOOTER_PLAIN
    return html, plain

COD = "{% if order.transactions.last.paymentMethod.technicalName == 'payment_cashpayment' %}"
templates = {}

templates['order_confirmation_mail'] = ("Xác nhận đơn hàng #{{ order.orderNumber }}", "Xác nhận đơn hàng", *order_mail(
    """    <p>Cảm ơn bạn đã đặt hàng tại {{ shop }}. Chúng tôi đã nhận đơn hàng <strong>#{{ order.orderNumber }}</strong> lúc {{ orderDate }}.</p>
    <p>""" + COD + """Bạn thanh toán <strong>{{ order.price.totalPrice|currency(currencyIsoCode) }}</strong> khi nhận hàng. {% endif %}Chúng tôi sẽ gửi email khi đơn hàng được giao cho đơn vị vận chuyển.</p>""",
    """Cảm ơn bạn đã đặt hàng tại {{ shop }}. Chúng tôi đã nhận đơn hàng #{{ order.orderNumber }} lúc {{ orderDate }}.
""" + COD + """Bạn thanh toán {{ order.price.totalPrice|currency(currencyIsoCode) }} khi nhận hàng. {% endif %}Chúng tôi sẽ gửi email khi đơn hàng được giao cho đơn vị vận chuyển.""",
    with_table=True, with_bank=True))

templates['order_transaction.state.paid'] = ("Đã nhận thanh toán đơn hàng #{{ order.orderNumber }}", "Đã nhận thanh toán", *order_mail(
    """    <p>Chúng tôi đã nhận được thanh toán cho đơn hàng <strong>#{{ order.orderNumber }}</strong> (đặt lúc {{ orderDate }}) và bắt đầu chuẩn bị hàng.</p>""",
    """Chúng tôi đã nhận được thanh toán cho đơn hàng #{{ order.orderNumber }} (đặt lúc {{ orderDate }}) và bắt đầu chuẩn bị hàng.""",
    with_table=True))

templates['order_transaction.state.reminded'] = ("Nhắc thanh toán đơn hàng #{{ order.orderNumber }}", "Nhắc thanh toán", *order_mail(
    """    <p>Đơn hàng <strong>#{{ order.orderNumber }}</strong> (đặt lúc {{ orderDate }}) chưa được thanh toán qua {{ order.transactions.last.paymentMethod.translated.name }}. Đơn hàng sẽ được chuẩn bị ngay khi cửa hàng nhận được tiền.</p>""",
    """Đơn hàng #{{ order.orderNumber }} (đặt lúc {{ orderDate }}) chưa được thanh toán qua {{ order.transactions.last.paymentMethod.translated.name }}. Đơn hàng sẽ được chuẩn bị ngay khi cửa hàng nhận được tiền.""",
    with_table=True, with_bank=True))

def state_mail(subject, desc, sentence_html, sentence_plain, state_expr):
    status_html = f"""    <p>Đơn hàng <strong>#{{{{ order.orderNumber }}}}</strong> (đặt lúc {{{{ orderDate }}}}) đã chuyển sang trạng thái: <strong>{{{{ {state_expr} }}}}</strong>.</p>
    <p>{sentence_html}</p>"""
    status_plain = f"""Đơn hàng #{{{{ order.orderNumber }}}} (đặt lúc {{{{ orderDate }}}}) đã chuyển sang trạng thái: {{{{ {state_expr} }}}}.
{sentence_plain}"""
    return (subject, desc, *order_mail(status_html, status_plain))

ORDER_STATE = "order.stateMachineState.translated.name"
DELIVERY_STATE = "order.deliveries.first.stateMachineState.translated.name"
PAYMENT_STATE = "order.transactions.last.stateMachineState.translated.name"

templates['order.state.in_progress'] = state_mail("Đơn hàng #{{ order.orderNumber }} đang được xử lý", "Đơn hàng đang xử lý",
    "Chúng tôi đang chuẩn bị hàng cho bạn.", "Chúng tôi đang chuẩn bị hàng cho bạn.", ORDER_STATE)
templates['order.state.completed'] = state_mail("Đơn hàng #{{ order.orderNumber }} đã hoàn tất", "Đơn hàng hoàn tất",
    "Cảm ơn bạn đã mua sắm tại {{ shop }}. Hẹn gặp lại bạn!", "Cảm ơn bạn đã mua sắm tại {{ shop }}. Hẹn gặp lại bạn!", ORDER_STATE)
templates['order.state.cancelled'] = state_mail("Đơn hàng #{{ order.orderNumber }} đã bị huỷ", "Đơn hàng bị huỷ",
    "Nếu bạn đã thanh toán, cửa hàng sẽ liên hệ để hoàn tiền. Nếu bạn không yêu cầu huỷ, hãy trả lời email này.",
    "Nếu bạn đã thanh toán, cửa hàng sẽ liên hệ để hoàn tiền. Nếu bạn không yêu cầu huỷ, hãy trả lời email này.", ORDER_STATE)
templates['order_delivery.state.shipped'] = state_mail("Đơn hàng #{{ order.orderNumber }} đang được giao", "Đã giao cho đơn vị vận chuyển",
    "Đơn hàng đã được giao cho đơn vị vận chuyển. Bạn để ý điện thoại để nhận hàng nhé." + COD + " Vui lòng chuẩn bị <strong>{{ order.price.totalPrice|currency(currencyIsoCode) }}</strong> để thanh toán khi nhận hàng.{% endif %}",
    "Đơn hàng đã được giao cho đơn vị vận chuyển. Bạn để ý điện thoại để nhận hàng nhé." + COD + " Vui lòng chuẩn bị {{ order.price.totalPrice|currency(currencyIsoCode) }} để thanh toán khi nhận hàng.{% endif %}", DELIVERY_STATE)
templates['order_delivery.state.cancelled'] = state_mail("Giao hàng đơn #{{ order.orderNumber }} đã bị huỷ", "Huỷ giao hàng",
    "Cửa hàng sẽ liên hệ với bạn về đơn hàng này.", "Cửa hàng sẽ liên hệ với bạn về đơn hàng này.", DELIVERY_STATE)
templates['order_transaction.state.cancelled'] = state_mail("Thanh toán đơn hàng #{{ order.orderNumber }} đã bị huỷ", "Huỷ thanh toán",
    "Nếu đây là nhầm lẫn, hãy trả lời email này để được hỗ trợ.", "Nếu đây là nhầm lẫn, hãy trả lời email này để được hỗ trợ.", PAYMENT_STATE)
templates['order.payment_method.changed'] = ("Đã đổi phương thức thanh toán đơn hàng #{{ order.orderNumber }}", "Đổi phương thức thanh toán", *order_mail(
    """    <p>Phương thức thanh toán của đơn hàng <strong>#{{ order.orderNumber }}</strong> đã được đổi sang: <strong>{{ order.transactions.last.paymentMethod.translated.name }}</strong>.</p>""",
    """Phương thức thanh toán của đơn hàng #{{ order.orderNumber }} đã được đổi sang: {{ order.transactions.last.paymentMethod.translated.name }}.""",
    with_bank=True))

def simple(subject, desc, html_body, plain_body):
    html = SHOP + "\n" + WRAP_OPEN + "\n" + html_body + "\n    <p>Trân trọng,<br>{{ shop }}</p>\n</div>"
    plain = SHOP + "\n" + plain_body + "\n\nTrân trọng,\n{{ shop }}"
    return (subject, desc, html, plain)

SHOP_INLINE = "{{ config('core.basicInformation.shopName') ?: salesChannel.translated.name }}"
templates['customer_register'] = simple("Chào mừng bạn đến với " + SHOP_INLINE, "Xác nhận đăng ký tài khoản",
    """    <p>Chào {{ customer.lastName }} {{ customer.firstName }},</p>
    <p>Cảm ơn bạn đã tạo tài khoản tại {{ shop }}. Bạn đăng nhập bằng email <strong>{{ customer.email }}</strong> và mật khẩu đã chọn; có thể đổi mật khẩu bất cứ lúc nào trong mục Tài khoản.</p>""",
    """Chào {{ customer.lastName }} {{ customer.firstName }},

Cảm ơn bạn đã tạo tài khoản tại {{ shop }}. Bạn đăng nhập bằng email {{ customer.email }} và mật khẩu đã chọn; có thể đổi mật khẩu bất cứ lúc nào trong mục Tài khoản.""")
templates['customer.recovery.request'] = simple("Đặt lại mật khẩu tài khoản " + SHOP_INLINE, "Khôi phục mật khẩu",
    """    <p>Chào {{ customerRecovery.customer.lastName }} {{ customerRecovery.customer.firstName }},</p>
    <p>Bạn vừa yêu cầu đặt lại mật khẩu tài khoản tại {{ shop }}. Bấm vào liên kết sau để đặt mật khẩu mới (có hiệu lực trong 2 giờ):</p>
    <p><a href="{{ resetUrl }}">{{ resetUrl }}</a></p>
    <p>Nếu bạn không yêu cầu, hãy bỏ qua email này — mật khẩu sẽ không thay đổi.</p>""",
    """Chào {{ customerRecovery.customer.lastName }} {{ customerRecovery.customer.firstName }},

Bạn vừa yêu cầu đặt lại mật khẩu tài khoản tại {{ shop }}. Mở liên kết sau để đặt mật khẩu mới (có hiệu lực trong 2 giờ):
{{ resetUrl }}

Nếu bạn không yêu cầu, hãy bỏ qua email này — mật khẩu sẽ không thay đổi.""")
templates['customer.password.changed'] = simple("Mật khẩu tài khoản đã được thay đổi", "Đã đổi mật khẩu",
    """    <p>Chào {{ customer.lastName }} {{ customer.firstName }},</p>
    <p>Mật khẩu tài khoản của bạn tại {{ shop }} vừa được thay đổi. Nếu không phải bạn thực hiện, hãy trả lời email này ngay.</p>""",
    """Chào {{ customer.lastName }} {{ customer.firstName }},

Mật khẩu tài khoản của bạn tại {{ shop }} vừa được thay đổi. Nếu không phải bạn thực hiện, hãy trả lời email này ngay.""")
templates['contact_form'] = simple("Liên hệ mới từ website: {{ contactFormData.subject }}", "Nhận liên hệ từ website",
    """    <p>Có tin nhắn mới gửi qua form liên hệ.</p>
    <p>
        Họ tên: {{ contactFormData.lastName }} {{ contactFormData.firstName }}<br>
        Email: {{ contactFormData.email }}<br>
        Điện thoại: {{ contactFormData.phone }}<br>
        Tiêu đề: {{ contactFormData.subject }}
    </p>
    <p>{{ contactFormData.comment|nl2br }}</p>""",
    """Có tin nhắn mới gửi qua form liên hệ.

Họ tên: {{ contactFormData.lastName }} {{ contactFormData.firstName }}
Email: {{ contactFormData.email }}
Điện thoại: {{ contactFormData.phone }}
Tiêu đề: {{ contactFormData.subject }}

{{ contactFormData.comment }}""")
templates['newsletterDoubleOptIn'] = simple("Xác nhận đăng ký nhận tin", "Xác nhận đăng ký nhận tin",
    """    <p>Chào {{ newsletterRecipient.lastName }} {{ newsletterRecipient.firstName }},</p>
    <p>Cảm ơn bạn đã quan tâm bản tin của {{ shop }}. Bấm vào liên kết sau để xác nhận đăng ký:</p>
    <p><a href="{{ url }}">Xác nhận đăng ký nhận tin</a></p>
    <p>Nếu bạn không đăng ký, hãy bỏ qua email này.</p>""",
    """Chào {{ newsletterRecipient.lastName }} {{ newsletterRecipient.firstName }},

Cảm ơn bạn đã quan tâm bản tin của {{ shop }}. Mở liên kết sau để xác nhận đăng ký:
{{ url }}

Nếu bạn không đăng ký, hãy bỏ qua email này.""")
templates['newsletterRegister'] = simple("Đăng ký nhận tin thành công", "Đăng ký nhận tin thành công",
    """    <p>Chào {{ newsletterRecipient.lastName }} {{ newsletterRecipient.firstName }},</p>
    <p>Bạn đã đăng ký nhận bản tin của {{ shop }} thành công. Cảm ơn bạn!</p>""",
    """Chào {{ newsletterRecipient.lastName }} {{ newsletterRecipient.firstName }},

Bạn đã đăng ký nhận bản tin của {{ shop }} thành công. Cảm ơn bạn!""")

import json
index = {}
for name, (subject, desc, html, plain) in templates.items():
    d = os.path.join(OUT, name)
    os.makedirs(d, exist_ok=True)
    open(os.path.join(d, "html.twig"), "w").write(html.strip() + "\n")
    open(os.path.join(d, "plain.twig"), "w").write(plain.strip() + "\n")
    index[name] = {"subject": subject, "description": desc}
open(os.path.join(OUT, "templates.json"), "w").write(json.dumps(index, ensure_ascii=False, indent=4) + "\n")
print(len(templates), "mẫu")
