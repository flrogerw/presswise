<?xml version="1.0"?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:template match="/">
		<HTML>
			<BODY
				STYLE="font-family:Arial, helvetica, sans-serif; font-size:9pt;
            background-color:#EEEEEE">

				<div>
					Total Orders Processed:
					<xsl:value-of select="Report/Summary/OrdersProcessed/TotalOrders" />
				</div>
				<div>
					Successful Orders Processed:
					<xsl:value-of select="Report/Summary/OrdersProcessed/Success" />
				</div>
				<div>
					Failed Orders:
					<xsl:value-of select="Report/Summary/OrdersProcessed/Failures" />
				</div>
				
				<BR />
				<xsl:for-each select="Report/SuccessDetails/Success">
					<DIV>
						<SPAN>
							<pre>
								<xsl:value-of select="." /> - Processed
							</pre>
						</SPAN>
					</DIV>
				</xsl:for-each>
				
				<BR />
				<xsl:for-each select="Report/ErrorDetails/Error">
				<div style="font-weight:bold">Error Reason: <xsl:value-of select="@ErrorType" /></div>
					<DIV STYLE="padding:4px">
						<SPAN>
							<pre>
								<xsl:value-of select="OrderXML" />
							</pre>
						</SPAN>
					</DIV>
				</xsl:for-each>
			</BODY>
		</HTML>
	</xsl:template>
</xsl:stylesheet>