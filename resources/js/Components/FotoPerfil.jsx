import { getThumbnailUrl } from "@/utils/globalFunctions";

export default function FotoPerfil({ image, alt, name, className, detail = false }) {
    let colors = [
        "bg-pink-700",
        "bg-green-700",
        "bg-yellow-700",
        "bg-red-700",
        "bg-orange-700",
        "bg-purple-700",
        "bg-teal-700",
        "bg-indigo-700",
        "bg-blue-700",
    ]
    const defaultStyles = "h-32 w-32 rounded-full border-2 border-white bg-blue-two object-cover p-2 text-lg"
    const fallbackStyles = className || defaultStyles;
    function getInitials(name) {
        if (!name) return "";

        const words = name.trim().split(" ");
        if (words.length === 1) {
            return words[0][0].toUpperCase();
        } else {
            return (
                words[0][0].toUpperCase() + words[words.length - 1][0].toUpperCase()
            );
        }
    }

    if (!image) {
        return (
            <span className={`${fallbackStyles} ${colors[Math.floor(Math.random() * colors.length)]} flex shrink-0 items-center justify-center text-sm font-semibold text-white`}>
                {getInitials(name)}
            </span>
        )
    }

    let tranformation = detail ? "c_fill,h_400,w_400" : "c_fill,h_200,w_200"

    return (
        <img src={getThumbnailUrl(image, tranformation)} alt={alt} className={className || defaultStyles} />
    )

}
