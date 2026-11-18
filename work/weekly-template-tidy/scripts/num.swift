import AppKit
let a=CommandLine.arguments
let img=NSImage(contentsOfFile:a[1])!
let rep=img.representations[0]; let w=rep.pixelsWide, h=rep.pixelsHigh
for i in 1...12 {
  let bm=NSBitmapImageRep(bitmapDataPlanes:nil,pixelsWide:w,pixelsHigh:h,bitsPerSample:8,samplesPerPixel:4,hasAlpha:true,isPlanar:false,colorSpaceName:.deviceRGB,bytesPerRow:0,bitsPerPixel:0)!
  NSGraphicsContext.saveGraphicsState(); NSGraphicsContext.current=NSGraphicsContext(bitmapImageRep:bm)
  img.draw(in:NSRect(x:0,y:0,width:w,height:h))
  let s=String(format:"%d",i) as NSString
  let f=NSFont.boldSystemFont(ofSize:CGFloat(h)*0.35)
  let attrs:[NSAttributedString.Key:Any]=[.font:f,.foregroundColor:NSColor.white,.strokeColor:NSColor(red:0,green:0.19,blue:0.34,alpha:1),.strokeWidth:-4]
  let sz=s.size(withAttributes:attrs)
  s.draw(at:NSPoint(x:(CGFloat(w)-sz.width)/2,y:(CGFloat(h)-sz.height)/2),withAttributes:attrs)
  NSGraphicsContext.restoreGraphicsState()
  let data=bm.representation(using:.jpeg,properties:[.compressionFactor:0.8])!
  try! data.write(to:URL(fileURLWithPath:String(format:"%@/Gallery-Placeholder-%02d.jpg",a[2],i)))
}
