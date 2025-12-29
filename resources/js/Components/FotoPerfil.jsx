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
    const defaultStyles = "w-32 h-32 w-full  bg-blue-two p-2 border-2 border-white rounded-full text-lg"
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

    if (image === null) {
        return (
            <span className={`w-full ${colors[Math.floor(Math.random() * colors.length)]} p-2 flex items-center justify-center border-2 border-white rounded-full text-white text-sm w-12 h-12 `}>{getInitials(name)}</span>
        )
    }

    if (image != null) {
        let tranformation = detail ? "c_fill,h_400,w_400" : "c_fill,h_200,w_200"
        return (
            <img src={getThumbnailUrl(image, tranformation)} alt={alt} className={className || defaultStyles} />
        )
    }


}